<?php

namespace Firevel\Filterable\Tests;

use Firevel\Filterable\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class MultipleFiltersTest extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('make');
            $table->integer('year');
            $table->integer('price');
            $table->integer('discount')->nullable();
            $table->timestamps();
        });

        Vehicle::create(['make' => 'Toyota', 'year' => 2020, 'price' => 20000]);
        Vehicle::create(['make' => 'Toyota', 'year' => 2024, 'price' => 30000]);
        Vehicle::create(['make' => 'Honda', 'year' => 2024, 'price' => 25000]);
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

    public function test_multiple_filters_are_combined_with_and()
    {
        $results = Vehicle::filter([
            'make' => 'Toyota',
            'year' => ['>=' => 2024],
        ])->get();

        $this->assertCount(1, $results);
        $this->assertEquals(30000, $results->first()->price);
    }

    public function test_three_filters_combined_with_and()
    {
        $results = Vehicle::filter([
            'make' => 'Toyota',
            'year' => 2024,
            'price' => ['>' => 25000],
        ])->get();

        $this->assertCount(1, $results);
    }

    public function test_filters_that_match_nothing_return_empty_result()
    {
        $results = Vehicle::filter([
            'make' => 'Honda',
            'year' => 2020,
        ])->get();

        $this->assertCount(0, $results);
    }

    public function test_empty_filters_array_returns_all_records()
    {
        $results = Vehicle::filter([])->get();

        $this->assertCount(3, $results);
    }

    public function test_operator_not_allowed_for_type_throws_exception()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Operator 'like' is not allowed for type 'integer'");

        Vehicle::filter(['year' => ['like' => '2024']])->get();
    }

    public function test_unknown_operator_key_throws_exception()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Illegal operator startswith');

        Vehicle::filter(['make' => ['startswith' => 'Toy']])->get();
    }

    public function test_unsupported_filter_type_throws_exception()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported filter type currency');

        Vehicle::filter(['discount' => 100])->get();
    }

    public function test_more_than_one_level_relationship_nesting_throws_exception()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Maximum one‐level sub‐query filtering supported.');

        Vehicle::filter(['owner.region.code' => 'US'])->get();
    }
}

class Vehicle extends Model
{
    use Filterable;

    protected $fillable = ['make', 'year', 'price', 'discount'];

    protected $filterable = [
        'make' => 'string',
        'year' => 'integer',
        'price' => 'integer',
        'discount' => 'currency',
        'owner.region.code' => 'string',
    ];
}
