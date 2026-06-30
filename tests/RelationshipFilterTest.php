<?php

namespace Firevel\Filterable\Tests;

use Firevel\Filterable\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class RelationshipFilterTest extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->string('status');
            $table->integer('amount');
            $table->timestamps();
        });

        $acme = Client::create(['name' => 'Acme']);
        $globex = Client::create(['name' => 'Globex']);
        Client::create(['name' => 'Initech']);

        Purchase::create(['client_id' => $acme->id, 'status' => 'shipped', 'amount' => 200]);
        Purchase::create(['client_id' => $acme->id, 'status' => 'pending', 'amount' => 50]);
        Purchase::create(['client_id' => $globex->id, 'status' => 'shipped', 'amount' => 50]);
        // Initech has no purchases at all.
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

    public function test_relationship_count_filter_with_greater_than()
    {
        $results = Client::filter(['purchases' => ['>' => 0]])->get();

        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing(['Acme', 'Globex'], $results->pluck('name')->all());
    }

    public function test_relationship_count_filter_excludes_clients_without_relation()
    {
        $results = Client::filter(['purchases' => ['>=' => 2]])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Acme', $results->first()->name);
    }

    public function test_relationship_dot_notation_filters_by_related_column()
    {
        $results = Client::filter(['purchases.status' => 'shipped'])->get();

        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing(['Acme', 'Globex'], $results->pluck('name')->all());
    }

    public function test_relationship_dot_notation_with_integer_column()
    {
        $results = Client::filter(['purchases.amount' => ['>' => 100]])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Acme', $results->first()->name);
    }

    public function test_combining_relationship_filter_with_direct_column_filter()
    {
        $results = Client::filter([
            'purchases.status' => 'shipped',
            'name' => 'Globex',
        ])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Globex', $results->first()->name);
    }

    public function test_use_relationship_query_adds_extra_constraint_to_dot_notation_filter()
    {
        $highValueOnly = function ($query) {
            $query->where('amount', '>', 100);
        };

        $results = Client::useRelationshipQuery($highValueOnly)
            ->filter(['purchases.status' => 'shipped'])
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Acme', $results->first()->name);
    }

    public function test_use_relationship_query_adds_extra_constraint_to_count_filter()
    {
        $highValueOnly = function ($query) {
            $query->where('amount', '>', 100);
        };

        $results = Client::useRelationshipQuery($highValueOnly)
            ->filter(['purchases' => ['>' => 0]])
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Acme', $results->first()->name);
    }

    public function test_use_relationship_query_mutates_an_existing_model_instance()
    {
        // useRelationshipQuery() can also be called on an already-built model
        // instance, with the constraint applying to that same instance even
        // if its return value isn't captured (e.g. `$model->useRelationshipQuery($fn);`
        // on its own line, followed by `$model->filter(...)` separately).
        $highValueOnly = function ($query) {
            $query->where('amount', '>', 100);
        };

        $model = new Client();
        $model->useRelationshipQuery($highValueOnly);

        $results = $model->filter(['purchases.status' => 'shipped'])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Acme', $results->first()->name);
    }

    public function test_relationship_in_operator_matches_related_primary_key()
    {
        $ids = Purchase::where('status', 'shipped')->pluck('id')->all();

        $results = Client::filter(['purchases' => ['in' => $ids]])->get();

        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing(['Acme', 'Globex'], $results->pluck('name')->all());
    }

    public function test_relationship_in_operator_with_use_relationship_query()
    {
        $highValueOnly = function ($query) {
            $query->where('amount', '>', 100);
        };

        $ids = Purchase::pluck('id')->all();

        $results = Client::useRelationshipQuery($highValueOnly)
            ->filter(['purchases' => ['in' => $ids]])
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Acme', $results->first()->name);
    }
}

class Client extends Model
{
    use Filterable;

    protected $fillable = ['name'];

    protected $filterable = [
        'name' => 'string',
        'purchases' => 'relationship',
        'purchases.status' => 'string',
        'purchases.amount' => 'integer',
    ];

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }
}

class Purchase extends Model
{
    protected $fillable = ['client_id', 'status', 'amount'];
}
