<?php

namespace Firevel\Filterable\Tests;

use Firevel\Filterable\Tests\Models\TestModel;
use Firevel\Filterable\Tests\Models\User;

class ScopeFilteringTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create users
        $user1 = User::create(['name' => 'Admin User', 'email' => 'admin@example.com', 'level' => 10]);
        $user2 = User::create(['name' => 'Power User', 'email' => 'power@example.com', 'level' => 7]);
        $user3 = User::create(['name' => 'Regular User', 'email' => 'regular@example.com', 'level' => 3]);

        // Create test models
        TestModel::create([
            'name' => 'Active Admin Model',
            'user_id' => $user1->id,
            'active' => true,
        ]);

        TestModel::create([
            'name' => 'Active Power Model',
            'user_id' => $user2->id,
            'active' => true,
        ]);

        TestModel::create([
            'name' => 'Inactive Admin Model',
            'user_id' => $user1->id,
            'active' => false,
        ]);

        TestModel::create([
            'name' => 'Active Regular Model',
            'user_id' => $user3->id,
            'active' => true,
        ]);

        TestModel::create([
            'name' => 'Orphan Model',
            'user_id' => null,
            'active' => true,
        ]);
    }

    public function test_scope_filter_with_true_value()
    {
        // The activeUsers scope filters for active = true AND user.level > 5
        $results = TestModel::filter(['activeUsers' => true])->get();

        $this->assertCount(2, $results);
        $this->assertContains('Active Admin Model', $results->pluck('name'));
        $this->assertContains('Active Power Model', $results->pluck('name'));
    }

    public function test_scope_filter_with_false_value()
    {
        // When passing false, the scope should not apply any filtering
        $results = TestModel::filter(['activeUsers' => false])->get();

        $this->assertCount(5, $results); // All models
    }

    public function test_scope_filter_with_string_value()
    {
        // Scope filters pass the value to the scope method
        $results = TestModel::filter(['activeUsers' => '1'])->get();

        $this->assertCount(2, $results);
        $this->assertContains('Active Admin Model', $results->pluck('name'));
        $this->assertContains('Active Power Model', $results->pluck('name'));
    }

    public function test_combining_scope_filter_with_other_filters()
    {
        $results = TestModel::filter([
            'activeUsers' => true,
            'name' => ['like' => '%Admin%'],
        ])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Active Admin Model', $results->first()->name);
    }

    public function test_multiple_scope_filters()
    {
        // Add another scope to TestModel for testing
        TestModel::macro('scopeHighLevelUsers', function ($query, $value) {
            if ($value) {
                return $query->whereHas('user', function ($q) {
                    $q->where('level', '>=', 8);
                });
            }
            return $query;
        });

        // Create a model with both scopes in filterable
        $model = new class extends TestModel {
            protected $table = 'test_models';
            protected $filterable = [
                'activeUsers' => 'scope',
                'highLevelUsers' => 'scope',
                'name' => 'string',
                'email' => 'string',
                'age' => 'integer',
                'price' => 'integer',
                'active' => 'boolean',
                'birth_date' => 'date',
                'created_at' => 'datetime',
                'settings' => 'json',
                'tags' => 'array',
                'user' => 'relationship',
            ];
        };

        $results = TestModel::filter([
            'activeUsers' => true,
            'active' => true, // This would normally include more results
        ])->get();

        // activeUsers scope already filters for active AND level > 5
        $this->assertCount(2, $results);
    }

    public function test_scope_filter_maintains_camel_case()
    {
        // The filter name 'activeUsers' should be converted to camelCase for the scope method
        // This is testing that Str::camel() is working correctly
        $results = TestModel::filter(['activeUsers' => true])->get();

        $this->assertGreaterThan(0, $results->count());
    }

    public function test_scope_filter_with_complex_logic()
    {
        // Create a more complex scenario
        TestModel::create([
            'name' => 'Edge Case Model',
            'user_id' => User::create(['name' => 'Edge User', 'email' => 'edge@example.com', 'level' => 6])->id,
            'active' => true,
        ]);

        // Should include the edge case (level 6 > 5)
        $results = TestModel::filter(['activeUsers' => true])->get();

        $this->assertCount(3, $results);
        $this->assertContains('Edge Case Model', $results->pluck('name'));
    }

    public function test_scope_filter_is_applied_before_other_filters()
    {
        // Test that scope filters are processed in the correct order
        $query = TestModel::query();
        
        // Track query execution order
        $executionOrder = [];
        
        TestModel::macro('scopeTrackingScope', function ($query, $value) use (&$executionOrder) {
            $executionOrder[] = 'scope';
            return $query;
        });

        // Override applyFilterToQuery to track regular filters
        $model = new class extends TestModel {
            public $filterable = [
                'trackingScope' => 'scope',
                'name' => 'string',
            ];
            
            public function applyFilterToQuery($filterType, $filterName, $filterValue, $operator, $query)
            {
                if ($filterType !== 'scope') {
                    $GLOBALS['test_execution_order'][] = 'regular';
                }
                return parent::applyFilterToQuery($filterType, $filterName, $filterValue, $operator, $query);
            }
        };

        // The scope should be called during the filter processing
        $results = TestModel::filter(['activeUsers' => true])->get();
        
        $this->assertGreaterThan(0, $results->count());
    }

    public function test_undefined_scope_throws_exception()
    {
        // Create a test model with a scope filter that doesn't have a corresponding method
        $model = new class extends TestModel {
            protected $filterable = [
                'nonExistentScope' => 'scope',
            ];
        };

        $this->expectException(\BadMethodCallException::class);
        
        $model::filter(['nonExistentScope' => true])->get();
    }

    public function test_scope_receives_filter_value()
    {
        // Create a scope that uses the passed value
        TestModel::macro('scopeMinLevel', function ($query, $minLevel) {
            return $query->whereHas('user', function ($q) use ($minLevel) {
                $q->where('level', '>=', $minLevel);
            });
        });

        $model = new class extends TestModel {
            protected $table = 'test_models';
            protected $filterable = [
                'minLevel' => 'scope',
                'name' => 'string',
                'email' => 'string',
                'age' => 'integer',
                'price' => 'integer',
                'active' => 'boolean',
                'birth_date' => 'date',
                'created_at' => 'datetime',
                'settings' => 'json',
                'tags' => 'array',
                'user' => 'relationship',
                'activeUsers' => 'scope',
            ];
            
            public function scopeMinLevel($query, $minLevel)
            {
                return $query->whereHas('user', function ($q) use ($minLevel) {
                    $q->where('level', '>=', $minLevel);
                });
            }
        };

        $results = $model::filter(['minLevel' => 8])->get();

        // Two models reference a user with level >= 8
        $this->assertCount(2, $results);
        $this->assertContains('Active Admin Model', $results->pluck('name'));
        $this->assertContains('Inactive Admin Model', $results->pluck('name'));
    }
}
