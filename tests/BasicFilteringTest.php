<?php

namespace Firevel\Filterable\Tests;

use Firevel\Filterable\Tests\Models\TestModel;
use Firevel\Filterable\Tests\Models\User;

class BasicFilteringTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        TestModel::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 25,
            'price' => 99.99,
            'active' => true,
        ]);

        TestModel::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'age' => 30,
            'price' => 149.99,
            'active' => false,
        ]);

        TestModel::create([
            'name' => 'Bob Johnson',
            'email' => 'bob@example.com',
            'age' => 35,
            'price' => 199.99,
            'active' => true,
        ]);
    }

    public function test_integer_filtering_with_equals_operator()
    {
        $results = TestModel::filter(['age' => 25])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results->first()->name);
    }

    public function test_integer_filtering_with_greater_than_operator()
    {
        $results = TestModel::filter(['age' => ['>' => 30]])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Bob Johnson', $results->first()->name);
    }

    public function test_integer_filtering_with_less_than_operator()
    {
        $results = TestModel::filter(['age' => ['<' => 30]])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results->first()->name);
    }

    public function test_integer_filtering_with_greater_than_or_equal_operator()
    {
        $results = TestModel::filter(['age' => ['>=' => 30]])->get();

        $this->assertCount(2, $results);
        $this->assertContains('Jane Smith', $results->pluck('name'));
        $this->assertContains('Bob Johnson', $results->pluck('name'));
    }

    public function test_integer_filtering_with_less_than_or_equal_operator()
    {
        $results = TestModel::filter(['age' => ['<=' => 30]])->get();

        $this->assertCount(2, $results);
        $this->assertContains('John Doe', $results->pluck('name'));
        $this->assertContains('Jane Smith', $results->pluck('name'));
    }

    public function test_integer_filtering_with_not_equal_operator()
    {
        $results = TestModel::filter(['age' => ['<>' => 30]])->get();

        $this->assertCount(2, $results);
        $this->assertContains('John Doe', $results->pluck('name'));
        $this->assertContains('Bob Johnson', $results->pluck('name'));
    }

    public function test_integer_filtering_with_in_operator()
    {
        $results = TestModel::filter(['age' => ['in' => '25,35']])->get();

        $this->assertCount(2, $results);
        $this->assertContains('John Doe', $results->pluck('name'));
        $this->assertContains('Bob Johnson', $results->pluck('name'));
    }

    public function test_integer_filtering_with_in_operator_array()
    {
        $results = TestModel::filter(['age' => ['in' => [25, 35]]])->get();

        $this->assertCount(2, $results);
        $this->assertContains('John Doe', $results->pluck('name'));
        $this->assertContains('Bob Johnson', $results->pluck('name'));
    }

    public function test_string_filtering_with_equals_operator()
    {
        $results = TestModel::filter(['name' => 'John Doe'])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('john@example.com', $results->first()->email);
    }

    public function test_string_filtering_with_like_operator()
    {
        $results = TestModel::filter(['name' => ['like' => '%John%']])->get();

        $this->assertCount(2, $results);
        $this->assertContains('John Doe', $results->pluck('name'));
        $this->assertContains('Bob Johnson', $results->pluck('name'));
    }

    public function test_string_filtering_with_not_equal_operator()
    {
        $results = TestModel::filter(['name' => ['<>' => 'John Doe']])->get();

        $this->assertCount(2, $results);
        $this->assertNotContains('John Doe', $results->pluck('name'));
    }

    public function test_boolean_filtering_true()
    {
        $results = TestModel::filter(['active' => true])->get();

        $this->assertCount(2, $results);
        $this->assertContains('John Doe', $results->pluck('name'));
        $this->assertContains('Bob Johnson', $results->pluck('name'));
    }

    public function test_boolean_filtering_false()
    {
        $results = TestModel::filter(['active' => false])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Jane Smith', $results->first()->name);
    }

    public function test_boolean_filtering_string_true()
    {
        $results = TestModel::filter(['active' => 'true'])->get();

        $this->assertCount(2, $results);
    }

    public function test_boolean_filtering_string_false()
    {
        $results = TestModel::filter(['active' => 'false'])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Jane Smith', $results->first()->name);
    }

    public function test_boolean_filtering_numeric_one()
    {
        $results = TestModel::filter(['active' => '1'])->get();

        $this->assertCount(2, $results);
    }

    public function test_boolean_filtering_numeric_zero()
    {
        $results = TestModel::filter(['active' => '0'])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Jane Smith', $results->first()->name);
    }

    public function test_multiple_filters()
    {
        $results = TestModel::filter([
            'age' => ['>=' => 30],
            'active' => true,
        ])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Bob Johnson', $results->first()->name);
    }

    public function test_null_filtering_with_is_operator()
    {
        TestModel::create(['name' => 'Null Test', 'age' => null]);

        $results = TestModel::filter(['age' => ['is' => 'null']])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Null Test', $results->first()->name);
    }

    public function test_null_filtering_with_not_operator()
    {
        TestModel::create(['name' => 'Null Test', 'age' => null]);

        $results = TestModel::filter(['age' => ['not' => 'null']])->get();

        $this->assertCount(3, $results);
        $this->assertNotContains('Null Test', $results->pluck('name'));
    }

    public function test_id_filtering()
    {
        $model = TestModel::first();

        $results = TestModel::filter(['id' => $model->id])->get();

        $this->assertCount(1, $results);
        $this->assertEquals($model->name, $results->first()->name);
    }

    public function test_price_filtering_as_integer_type()
    {
        $results = TestModel::filter(['price' => ['>' => 100]])->get();

        $this->assertCount(2, $results);
        $this->assertContains('Jane Smith', $results->pluck('name'));
        $this->assertContains('Bob Johnson', $results->pluck('name'));
    }

    public function test_empty_filters_returns_all()
    {
        $results = TestModel::filter([])->get();

        $this->assertCount(3, $results);
    }

    public function test_filter_with_undeclared_column_is_skipped()
    {
        $results = TestModel::filter(['undeclared_column' => 'value'])->get();

        $this->assertCount(3, $results);
    }
}