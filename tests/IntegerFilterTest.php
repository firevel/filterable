<?php

namespace Firevel\Filterable\Tests;

use Firevel\Filterable\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class IntegerFilterTest extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->integer('quantity');
            $table->integer('reorder_level')->nullable();
            $table->timestamps();
        });

        StockItem::create(['quantity' => 5, 'reorder_level' => 10]);
        StockItem::create(['quantity' => 15, 'reorder_level' => null]);
        StockItem::create(['quantity' => 30, 'reorder_level' => 20]);
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
        $results = StockItem::filter(['quantity' => 15])->get();

        $this->assertCount(1, $results);
        $this->assertEquals(15, $results->first()->quantity);
    }

    public function test_not_equal_operator()
    {
        $results = StockItem::filter(['quantity' => ['<>' => 15]])->get();

        $this->assertCount(2, $results);
    }

    public function test_ne_alias_operator()
    {
        $results = StockItem::filter(['quantity' => ['ne' => 15]])->get();

        $this->assertCount(2, $results);
    }

    public function test_greater_than_operator()
    {
        $results = StockItem::filter(['quantity' => ['>' => 10]])->get();

        $this->assertCount(2, $results);
    }

    public function test_gt_alias_operator()
    {
        $results = StockItem::filter(['quantity' => ['gt' => 10]])->get();

        $this->assertCount(2, $results);
    }

    public function test_greater_than_or_equal_operator()
    {
        $results = StockItem::filter(['quantity' => ['>=' => 15]])->get();

        $this->assertCount(2, $results);
    }

    public function test_gte_alias_operator()
    {
        $results = StockItem::filter(['quantity' => ['gte' => 15]])->get();

        $this->assertCount(2, $results);
    }

    public function test_less_than_operator()
    {
        $results = StockItem::filter(['quantity' => ['<' => 15]])->get();

        $this->assertCount(1, $results);
        $this->assertEquals(5, $results->first()->quantity);
    }

    public function test_lt_alias_operator()
    {
        $results = StockItem::filter(['quantity' => ['lt' => 15]])->get();

        $this->assertCount(1, $results);
    }

    public function test_less_than_or_equal_operator()
    {
        $results = StockItem::filter(['quantity' => ['<=' => 15]])->get();

        $this->assertCount(2, $results);
    }

    public function test_lte_alias_operator()
    {
        $results = StockItem::filter(['quantity' => ['lte' => 15]])->get();

        $this->assertCount(2, $results);
    }

    public function test_in_operator_with_comma_separated_string()
    {
        $results = StockItem::filter(['quantity' => ['in' => '5,30']])->get();

        $this->assertCount(2, $results);
    }

    public function test_in_operator_with_array()
    {
        $results = StockItem::filter(['quantity' => ['in' => [5, 30]]])->get();

        $this->assertCount(2, $results);
    }

    public function test_is_null_operator()
    {
        $results = StockItem::filter(['reorder_level' => ['is' => 'null']])->get();

        $this->assertCount(1, $results);
        $this->assertEquals(15, $results->first()->quantity);
    }

    public function test_not_null_operator()
    {
        $results = StockItem::filter(['reorder_level' => ['not' => 'null']])->get();

        $this->assertCount(2, $results);
    }

    public function test_id_type_filter()
    {
        $first = StockItem::first();

        $results = StockItem::filter(['id' => $first->id])->get();

        $this->assertCount(1, $results);
        $this->assertEquals($first->id, $results->first()->id);
    }

    public function test_id_type_in_operator()
    {
        $ids = StockItem::pluck('id')->take(2)->all();

        $results = StockItem::filter(['id' => ['in' => $ids]])->get();

        $this->assertCount(2, $results);
    }
}

class StockItem extends Model
{
    use Filterable;

    protected $fillable = ['quantity', 'reorder_level'];

    protected $filterable = [
        'id' => 'id',
        'quantity' => 'integer',
        'reorder_level' => 'integer',
    ];
}
