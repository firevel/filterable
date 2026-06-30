<?php

namespace Firevel\Filterable\Tests;

use Firevel\Filterable\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class ScopeFilterTest extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('contributors', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('team');
            $table->timestamps();
        });

        Contributor::create(['first_name' => 'John', 'last_name' => 'Doe', 'team' => 'backend']);
        Contributor::create(['first_name' => 'Jane', 'last_name' => 'Smith', 'team' => 'frontend']);
        Contributor::create(['first_name' => 'Bob', 'last_name' => 'Doe', 'team' => 'backend']);
        Contributor::create(['first_name' => 'prefixed', 'last_name' => 'Marker', 'team' => 'ops']);
        Contributor::create(['first_name' => 'simple', 'last_name' => 'Marker', 'team' => 'ops']);
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    public function test_prefixed_scope_filter_combines_two_columns_with_or()
    {
        $results = Contributor::filter(['name' => ['like' => 'Doe']])->get();

        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing(['John', 'Bob'], $results->pluck('first_name')->all());
    }

    public function test_prefixed_scope_filter_accepts_plain_scalar_too()
    {
        $results = Contributor::filter(['name' => 'Doe'])->get();

        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing(['John', 'Bob'], $results->pluck('first_name')->all());
    }

    public function test_simple_scope_naming_convention_with_underscore_key()
    {
        // Filter key "team_search" has no scopeFilterTeamSearch method, only
        // scopeTeamSearch, so it must fall back to the simple convention.
        $results = Contributor::filter(['team_search' => 'backend'])->get();

        $this->assertCount(2, $results);
    }

    public function test_prefixed_convention_takes_precedence_over_simple_convention()
    {
        // "priority" has both scopeFilterPriority and scopePriority defined;
        // the prefixed one must win.
        $results = Contributor::filter(['priority' => 'anything'])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('prefixed', $results->first()->first_name);
    }

    public function test_scope_receives_all_filters_as_second_argument()
    {
        $results = Contributor::filter([
            'search' => 'Doe',
            'team' => 'backend',
        ])->get();

        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing(['John', 'Bob'], $results->pluck('first_name')->all());
    }

    public function test_scope_without_team_filter_present_only_matches_name()
    {
        $results = Contributor::filter(['search' => 'Doe'])->get();

        $this->assertCount(2, $results);
    }

    public function test_missing_scope_method_throws_exception()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches("/Scope method '.*' or '.*' not found on model\\./");

        Contributor::filter(['undeclared_scope' => 'x'])->get();
    }
}

class Contributor extends Model
{
    use Filterable;

    protected $fillable = ['first_name', 'last_name', 'team'];

    protected $filterable = [
        'first_name' => 'string',
        'last_name' => 'string',
        'team' => 'string',
        'name' => 'scope',
        'team_search' => 'scope',
        'priority' => 'scope',
        'search' => 'scope',
        'undeclared_scope' => 'scope',
    ];

    public function scopeFilterName($query, $value, $allFilters = [])
    {
        $query->where(function ($q) use ($value) {
            $q->where('first_name', 'like', "%{$value}%")
              ->orWhere('last_name', 'like', "%{$value}%");
        });
    }

    public function scopeTeamSearch($query, $value, $allFilters = [])
    {
        $query->where('team', $value);
    }

    public function scopeFilterPriority($query, $value, $allFilters = [])
    {
        $query->where('first_name', 'prefixed');
    }

    public function scopePriority($query, $value, $allFilters = [])
    {
        $query->where('first_name', 'simple');
    }

    public function scopeFilterSearch($query, $value, $allFilters = [])
    {
        $query->where(function ($q) use ($value, $allFilters) {
            $q->where('first_name', 'like', "%{$value}%")
              ->orWhere('last_name', 'like', "%{$value}%");

            if (isset($allFilters['team'])) {
                $q->orWhere('team', $allFilters['team']);
            }
        });
    }
}
