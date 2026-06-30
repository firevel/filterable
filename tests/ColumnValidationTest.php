<?php

namespace Firevel\Filterable\Tests;

use Firevel\Filterable\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class ColumnValidationTest extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->string('status');
            $table->timestamps();
        });

        Ticket::create(['subject' => 'Cannot login', 'status' => 'open']);
        Ticket::create(['subject' => 'Billing question', 'status' => 'closed']);
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

    public function test_unknown_filter_is_silently_ignored_by_default()
    {
        $results = Ticket::filter(['not_a_column' => 5])->get();

        $this->assertCount(2, $results);
    }

    public function test_known_filter_still_applies_with_default_validation()
    {
        $results = Ticket::filter(['status' => 'open'])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Cannot login', $results->first()->subject);
    }

    public function test_strict_validation_throws_for_unknown_column()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Filter column 'not_a_column' is not allowed.");

        StrictTicket::filter(['not_a_column' => 5])->get();
    }

    public function test_strict_validation_throws_using_base_column_of_json_path()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Filter column 'meta' is not allowed.");

        StrictTicket::filter(['meta->role' => 'admin'])->get();
    }

    public function test_strict_validation_still_allows_declared_columns()
    {
        $results = StrictTicket::filter(['status' => 'open'])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Cannot login', $results->first()->subject);
    }
}

class Ticket extends Model
{
    use Filterable;

    protected $fillable = ['subject', 'status'];

    protected $filterable = [
        'subject' => 'string',
        'status' => 'string',
    ];
}

class StrictTicket extends Model
{
    use Filterable;

    protected $table = 'tickets';

    protected $validateColumns = true;

    protected $fillable = ['subject', 'status'];

    protected $filterable = [
        'subject' => 'string',
        'status' => 'string',
    ];
}
