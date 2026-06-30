<?php

namespace Firevel\Filterable\Tests;

use Firevel\Filterable\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class JsonFilterTest extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        UserProfile::create(['meta' => ['role' => 'admin', 'department' => 'engineering']]);
        UserProfile::create(['meta' => ['role' => 'editor', 'department' => 'sales']]);
        UserProfile::create(['meta' => null]);
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

    public function test_json_path_equals_filters_by_nested_key()
    {
        $results = UserProfile::filter(['meta->role' => 'admin'])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('admin', $results->first()->meta['role']);
    }

    public function test_json_path_in_operator_matches_any_value()
    {
        $results = UserProfile::filter(['meta->role' => ['in' => 'admin,editor']])->get();

        $this->assertCount(2, $results);
    }

    public function test_json_column_is_null()
    {
        $results = UserProfile::filter(['meta' => ['is' => 'null']])->get();

        $this->assertCount(1, $results);
        $this->assertNull($results->first()->meta);
    }

    public function test_json_column_not_null()
    {
        $results = UserProfile::filter(['meta' => ['not' => 'null']])->get();

        $this->assertCount(2, $results);
    }
}

class UserProfile extends Model
{
    use Filterable;

    protected $fillable = ['meta'];

    protected $casts = [
        'meta' => 'array',
    ];

    protected $filterable = [
        'meta' => 'json',
    ];
}
