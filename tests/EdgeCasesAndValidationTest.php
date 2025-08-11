<?php

namespace Firevel\Filterable\Tests;

use Firevel\Filterable\Tests\Models\TestModel;
use Firevel\Filterable\Tests\Models\ValidatedModel;
use Firevel\Filterable\Tests\Models\User;

class EdgeCasesAndValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TestModel::create(['name' => 'Test Model 1', 'email' => 'test1@example.com']);
        TestModel::create(['name' => 'Test Model 2', 'email' => 'test2@example.com']);
    }

    public function test_empty_filterable_array_returns_all_records()
    {
        $model = new class extends TestModel {
            protected $table = 'test_models';
            protected $filterable = [];
        };

        $results = $model::filter(['name' => 'Test Model 1'])->get();

        // Should return all records since no filters are defined
        $this->assertCount(2, $results);
    }

    public function test_null_filterable_property_returns_all_records()
    {
        $model = new class extends TestModel {
            protected $table = 'test_models';
            protected $filterable = null;
        };

        $results = $model::filter(['name' => 'Test Model 1'])->get();

        // Should return all records since filterable is null
        $this->assertCount(2, $results);
    }

    public function test_column_validation_enabled_with_valid_column()
    {
        ValidatedModel::create(['name' => 'Validated 1', 'email' => 'validated1@example.com']);
        ValidatedModel::create(['name' => 'Validated 2', 'email' => 'validated2@example.com']);

        $results = ValidatedModel::filter(['name' => 'Validated 1'])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Validated 1', $results->first()->name);
    }

    public function test_column_validation_enabled_with_invalid_column()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Filter column 'age' is not allowed.");

        ValidatedModel::filter(['age' => 25])->get();
    }

    public function test_column_validation_with_json_path()
    {
        $this->markTestSkipped('JSON path validation tests deferred.');
    }

    public function test_column_validation_with_invalid_json_path()
    {
        $this->markTestSkipped('JSON path validation tests deferred.');
    }

    public function test_illegal_operator_throws_exception()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Illegal operator @');

        TestModel::filter(['name' => ['@' => 'test']])->get();
    }

    public function test_operator_not_allowed_for_type_throws_exception()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Operator 'like' is not allowed for type 'integer'");

        TestModel::filter(['age' => ['like' => '%25%']])->get();
    }

    public function test_unsupported_filter_type_throws_exception()
    {
        $model = new class extends TestModel {
            protected $table = 'test_models';
            protected $filterable = [
                'custom' => 'unsupported_type',
            ];
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported filter type unsupported_type');

        $model::filter(['custom' => 'value'])->get();
    }

    public function test_url_encoded_operators()
    {
        // Test that URL-encoded operators are properly decoded
        TestModel::create(['name' => 'Encoded Test', 'age' => 40]);

        $results = TestModel::filter(['age' => ['%3E' => 35]])->get(); // %3E is >

        $this->assertCount(1, $results);
        $this->assertEquals('Encoded Test', $results->first()->name);
    }

    public function test_filter_with_closure_in_relationship_query()
    {
        $user = User::create(['name' => 'Special User', 'email' => 'special@example.com', 'level' => 8]);
        TestModel::create(['name' => 'Special Model', 'user_id' => $user->id]);

        $model = new TestModel();
        $query = TestModel::query();

        // Test with relationship query
        $model->useRelationshipQuery(function($q) {
            $q->where('email', 'like', '%special%');
        });

        $model->applyFiltersToQuery(['user' => ['>=' => 1]], $query);
        $results = $query->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Special Model', $results->first()->name);
    }

    public function test_multiple_operators_on_same_field()
    {
        TestModel::create(['name' => 'Range Test 1', 'age' => 20]);
        TestModel::create(['name' => 'Range Test 2', 'age' => 30]);
        TestModel::create(['name' => 'Range Test 3', 'age' => 40]);

        // Test multiple operators on the same field
        $results = TestModel::filter([
            'age' => [
                '>=' => 25,
                '<=' => 35,
            ],
        ])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Range Test 2', $results->first()->name);
    }

    public function test_special_characters_in_filter_values()
    {
        TestModel::create(['name' => "O'Brien", 'email' => "o'brien@example.com"]);
        TestModel::create(['name' => 'Test & Co.', 'email' => 'test&co@example.com']);
        TestModel::create(['name' => '50% off', 'email' => '50percent@example.com']);

        // Test with special characters
        $results = TestModel::filter(['name' => "O'Brien"])->get();
        $this->assertCount(1, $results);

        $results = TestModel::filter(['name' => ['like' => '%&%']])->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Test & Co.', $results->first()->name);

        $results = TestModel::filter(['name' => ['like' => '50%']])->get();
        $this->assertCount(1, $results);
        $this->assertEquals('50% off', $results->first()->name);
    }

    public function test_very_long_json_paths()
    {
        $this->markTestSkipped('Deep JSON path tests deferred.');
    }

    public function test_filter_preserves_query_builder_state()
    {
        TestModel::create(['name' => 'Active Test', 'active' => true, 'age' => 25]);
        TestModel::create(['name' => 'Inactive Test', 'active' => false, 'age' => 30]);

        // Test that filter() preserves existing query conditions
        $results = TestModel::where('active', true)
                           ->filter(['age' => ['>=' => 20]])
                           ->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Active Test', $results->first()->name);
    }

    public function test_filter_with_empty_string_value()
    {
        TestModel::create(['name' => '', 'email' => 'empty@example.com']);
        TestModel::create(['name' => 'Not Empty', 'email' => 'notempty@example.com']);

        $results = TestModel::filter(['name' => ''])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('empty@example.com', $results->first()->email);
    }

    public function test_filter_with_zero_values()
    {
        TestModel::create(['name' => 'Zero Age', 'age' => 0]);
        TestModel::create(['name' => 'Non Zero', 'age' => 25]);

        $results = TestModel::filter(['age' => 0])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Zero Age', $results->first()->name);
    }

    public function test_case_sensitivity_in_operators()
    {
        // Test that operators are case-insensitive if needed
        TestModel::create(['name' => 'Null Test Upper', 'age' => null]);
        TestModel::create(['name' => 'Not Null', 'age' => 25]);

        // Test with uppercase NULL - should find all null age records
        $results = TestModel::filter(['age' => ['is' => 'NULL']])->get();
        // Could be more than 1 if other tests created records with null age
        $this->assertGreaterThanOrEqual(1, $results->count());
        $this->assertContains('Null Test Upper', $results->pluck('name'));

        // Test with mixed case
        $results = TestModel::filter(['age' => ['not' => 'NuLl']])->get();
        // Robust assertion: should include non-null age we just created
        $this->assertContains('Not Null', $results->pluck('name'));
        // and should not include the null-age record we created
        $this->assertNotContains('Null Test Upper', $results->pluck('name'));
        $this->assertGreaterThanOrEqual(1, $results->count());
    }

    public function test_default_operator_override()
    {
        $model = new class extends TestModel {
            protected $table = 'test_models';
            protected $defaultFilterOperator = 'like';
            protected $filterable = [
                'name' => 'string',
            ];
        };

        $model::create(['name' => 'Default Like Test']);
        $model::create(['name' => 'Another Test']);

        // Should use LIKE as default operator
        $results = $model::filter(['name' => '%Test'])->get();

        $this->assertCount(2, $results);
    }
}
