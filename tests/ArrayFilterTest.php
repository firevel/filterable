<?php

namespace Firevel\Filterable\Tests;

use Firevel\Filterable\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class ArrayFilterTest extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->json('tags')->nullable();
            $table->timestamps();
        });

        Listing::create(['title' => 'Premium Villa', 'tags' => ['premium', 'vip']]);
        Listing::create(['title' => 'Budget Flat', 'tags' => ['basic']]);
        Listing::create(['title' => 'Premium Apartment', 'tags' => ['premium']]);
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

    public function test_in_operator_with_single_value_checks_array_contains()
    {
        $results = Listing::filter(['tags' => ['in' => 'premium']])->get();

        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing(
            ['Premium Villa', 'Premium Apartment'],
            $results->pluck('title')->all()
        );
    }

    public function test_in_operator_with_multiple_values_matches_any()
    {
        $results = Listing::filter(['tags' => ['in' => 'premium,vip']])->get();

        $this->assertCount(2, $results);
    }

    public function test_in_operator_with_value_not_present_returns_no_results()
    {
        $results = Listing::filter(['tags' => ['in' => 'luxury']])->get();

        $this->assertCount(0, $results);
    }

    public function test_equals_operator_matches_exact_array()
    {
        $results = Listing::filter(['tags' => ['=' => json_encode(['premium', 'vip'])]])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Premium Villa', $results->first()->title);
    }
}

class Listing extends Model
{
    use Filterable;

    protected $fillable = ['title', 'tags'];

    protected $casts = [
        'tags' => 'array',
    ];

    protected $filterable = [
        'title' => 'string',
        'tags' => 'array',
    ];
}
