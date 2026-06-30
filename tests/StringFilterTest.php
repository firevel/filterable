<?php

namespace Firevel\Filterable\Tests;

use Firevel\Filterable\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class StringFilterTest extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category');
            $table->string('summary')->nullable();
            $table->timestamps();
        });

        Article::create(['title' => 'Laravel Tips', 'category' => 'tech', 'summary' => 'Useful laravel tips']);
        Article::create(['title' => 'Cooking Basics', 'category' => 'food', 'summary' => null]);
        Article::create(['title' => 'Advanced Laravel', 'category' => 'tech', 'summary' => 'Deep dive']);
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

    public function test_default_operator_is_equals()
    {
        $results = Article::filter(['title' => 'Laravel Tips'])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Laravel Tips', $results->first()->title);
    }

    public function test_explicit_equals_operator()
    {
        $results = Article::filter(['category' => ['=' => 'tech']])->get();

        $this->assertCount(2, $results);
    }

    public function test_not_equal_operator()
    {
        $results = Article::filter(['category' => ['<>' => 'tech']])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Cooking Basics', $results->first()->title);
    }

    public function test_like_operator()
    {
        $results = Article::filter(['title' => ['like' => '%Laravel%']])->get();

        $this->assertCount(2, $results);
    }

    public function test_eq_alias_operator()
    {
        $results = Article::filter(['category' => ['eq' => 'food']])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Cooking Basics', $results->first()->title);
    }

    public function test_ne_alias_operator()
    {
        $results = Article::filter(['category' => ['ne' => 'food']])->get();

        $this->assertCount(2, $results);
    }

    public function test_in_operator_with_comma_separated_string()
    {
        $results = Article::filter(['title' => ['in' => 'Laravel Tips,Cooking Basics']])->get();

        $this->assertCount(2, $results);
    }

    public function test_in_operator_with_array()
    {
        $results = Article::filter(['title' => ['in' => ['Laravel Tips', 'Advanced Laravel']]])->get();

        $this->assertCount(2, $results);
    }

    public function test_is_null_operator()
    {
        $results = Article::filter(['summary' => ['is' => 'null']])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Cooking Basics', $results->first()->title);
    }

    public function test_not_null_operator()
    {
        $results = Article::filter(['summary' => ['not' => 'null']])->get();

        $this->assertCount(2, $results);
    }

    public function test_unknown_filter_key_is_ignored_by_default()
    {
        $results = Article::filter(['not_a_filterable_column' => 'whatever'])->get();

        $this->assertCount(3, $results);
    }

    public function test_combining_multiple_string_filters_uses_and()
    {
        $results = Article::filter([
            'category' => 'tech',
            'title' => ['like' => '%Advanced%'],
        ])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Advanced Laravel', $results->first()->title);
    }
}

class Article extends Model
{
    use Filterable;

    protected $fillable = ['title', 'category', 'summary'];

    protected $filterable = [
        'title' => 'string',
        'category' => 'string',
        'summary' => 'string',
    ];
}
