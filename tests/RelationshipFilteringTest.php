<?php

namespace Firevel\Filterable\Tests;

use Firevel\Filterable\Tests\Models\TestModel;
use Firevel\Filterable\Tests\Models\User;
use Firevel\Filterable\Tests\Models\Post;
use Firevel\Filterable\Tests\Models\Comment;

class RelationshipFilteringTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create users
        $user1 = User::create(['name' => 'John Doe', 'email' => 'john@example.com', 'level' => 5]);
        $user2 = User::create(['name' => 'Jane Smith', 'email' => 'jane@example.com', 'level' => 10]);
        $user3 = User::create(['name' => 'Bob Johnson', 'email' => 'bob@example.com', 'level' => 3]);

        // Create test models with user relationships
        TestModel::create(['name' => 'Model 1', 'user_id' => $user1->id, 'active' => true]);
        TestModel::create(['name' => 'Model 2', 'user_id' => $user2->id, 'active' => true]);
        TestModel::create(['name' => 'Model 3', 'user_id' => $user3->id, 'active' => false]);
        TestModel::create(['name' => 'Model 4', 'user_id' => null, 'active' => true]);

        // Create posts
        $post1 = Post::create(['title' => 'Post 1', 'content' => 'Content 1', 'user_id' => $user1->id]);
        $post2 = Post::create(['title' => 'Post 2', 'content' => 'Content 2', 'user_id' => $user2->id]);
        $post3 = Post::create(['title' => 'Post 3', 'content' => 'Content 3', 'user_id' => $user2->id]);

        // Create comments
        Comment::create(['content' => 'Comment 1', 'post_id' => $post1->id, 'user_id' => $user2->id]);
        Comment::create(['content' => 'Comment 2', 'post_id' => $post1->id, 'user_id' => $user3->id]);
        Comment::create(['content' => 'Comment 3', 'post_id' => $post2->id, 'user_id' => $user1->id]);
    }

    public function test_basic_relationship_filtering()
    {
        // Filter test models that have a user
        $results = TestModel::filter(['user' => ['>=' => 1]])->get();

        $this->assertCount(3, $results);
        $this->assertNotContains('Model 4', $results->pluck('name'));
    }

    public function test_relationship_filtering_with_dot_notation()
    {
        // Filter test models by user email
        $results = TestModel::filter(['user.email' => 'jane@example.com'])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Model 2', $results->first()->name);
    }

    public function test_relationship_filtering_with_like_operator()
    {
        // Filter test models by user name pattern
        $results = TestModel::filter(['user.name' => ['like' => '%John%']])->get();

        $this->assertCount(2, $results);
        $this->assertContains('Model 1', $results->pluck('name'));
        $this->assertContains('Model 3', $results->pluck('name'));
    }

    public function test_relationship_filtering_with_integer_comparison()
    {
        // Filter test models by user level
        $results = TestModel::filter(['user.level' => ['>' => 5]])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Model 2', $results->first()->name);
    }

    public function test_has_many_relationship_filtering()
    {
        // Filter users that have posts
        $results = User::filter(['posts' => ['>=' => 1]])->get();

        $this->assertCount(2, $results);
        $this->assertContains('John Doe', $results->pluck('name'));
        $this->assertContains('Jane Smith', $results->pluck('name'));
        $this->assertNotContains('Bob Johnson', $results->pluck('name'));
    }

    public function test_has_many_relationship_filtering_with_count()
    {
        // Filter users that have more than 1 post
        $results = User::filter(['posts' => ['>' => 1]])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Jane Smith', $results->first()->name);
    }

    public function test_nested_relationship_filtering()
    {
        // Filter posts that have comments from a specific user
        $results = Post::filter(['comments.user_id' => User::where('email', 'jane@example.com')->first()->id])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Post 1', $results->first()->title);
    }

    public function test_multiple_relationship_filters()
    {
        // Filter test models with active status and user level > 3
        $results = TestModel::filter([
            'active' => true,
            'user.level' => ['>' => 3],
        ])->get();

        $this->assertCount(2, $results);
        $this->assertContains('Model 1', $results->pluck('name'));
        $this->assertContains('Model 2', $results->pluck('name'));
    }

    public function test_relationship_filtering_with_null_check()
    {
        // Create a post without comments
        Post::create(['title' => 'Post 4', 'content' => 'Content 4', 'user_id' => User::first()->id]);

        // Filter posts that have no comments
        $results = Post::filter(['comments' => ['=' => 0]])->get();

        $this->assertCount(2, $results);
        $this->assertContains('Post 3', $results->pluck('title'));
        $this->assertContains('Post 4', $results->pluck('title'));
    }

    public function test_relationship_filtering_with_whereHas()
    {
        // This tests the internal whereHas functionality
        // Filter posts by user name through relationship
        $results = Post::filter(['user.name' => 'Jane Smith'])->get();

        $this->assertCount(2, $results);
        $this->assertContains('Post 2', $results->pluck('title'));
        $this->assertContains('Post 3', $results->pluck('title'));
    }

    public function test_relationship_filtering_with_multiple_conditions()
    {
        // Filter posts by user with multiple conditions
        $results = Post::filter([
            'user.email' => ['like' => '%@example.com'],
            'user.level' => ['>=' => 5],
        ])->get();

        $this->assertCount(3, $results);
    }

    public function test_relationship_query_with_extra_conditions()
    {
        // Create a TestModel instance to test useRelationshipQuery
        $model = new TestModel();
        
        // Apply relationship query filter
        $query = TestModel::query();
        $model->useRelationshipQuery(function($q) {
            $q->where('level', '>', 5);
        });

        // Apply filters
        $model->applyFiltersToQuery(['user' => ['>=' => 1]], $query);
        $results = $query->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Model 2', $results->first()->name);
    }

    public function test_relationship_filtering_throws_exception_for_nested_relationships()
    {
        try {
            TestModel::filter(['user.posts.title' => 'test'])->get();
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Maximum one', $e->getMessage());
            $this->assertStringContainsString('level sub', $e->getMessage());
            $this->assertStringContainsString('query filtering supported', $e->getMessage());
        }
    }

    public function test_relationship_filtering_converts_to_camel_case()
    {
        // Create a model with snake_case relationship
        // The relationship 'user' should work even if column is 'user_id'
        $results = TestModel::filter(['user' => ['>=' => 1]])->get();

        $this->assertCount(3, $results);
    }

    // JSON-based relationship filtering deferred for now
}
