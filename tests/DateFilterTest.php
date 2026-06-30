<?php

namespace Firevel\Filterable\Tests;

use Firevel\Filterable\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class DateFilterTest extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('reference');
            $table->date('issued_on')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
        });

        Invoice::create(['reference' => 'INV-1', 'issued_on' => '2024-01-05', 'paid_at' => '2024-01-05 10:00:00']);
        Invoice::create(['reference' => 'INV-2', 'issued_on' => '2024-02-10', 'paid_at' => '2024-02-10 12:30:00']);
        Invoice::create(['reference' => 'INV-3', 'issued_on' => null, 'paid_at' => null]);
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

    public function test_date_equals()
    {
        $results = Invoice::filter(['issued_on' => '2024-01-05'])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('INV-1', $results->first()->reference);
    }

    public function test_date_greater_than()
    {
        $results = Invoice::filter(['issued_on' => ['>' => '2024-01-01']])->get();

        $this->assertCount(2, $results);
    }

    public function test_date_greater_than_or_equal()
    {
        $results = Invoice::filter(['issued_on' => ['>=' => '2024-02-10']])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('INV-2', $results->first()->reference);
    }

    public function test_date_less_than()
    {
        $results = Invoice::filter(['issued_on' => ['<' => '2024-02-10']])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('INV-1', $results->first()->reference);
    }

    public function test_date_less_than_or_equal()
    {
        $results = Invoice::filter(['issued_on' => ['<=' => '2024-01-05']])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('INV-1', $results->first()->reference);
    }

    public function test_date_is_null()
    {
        $results = Invoice::filter(['issued_on' => ['is' => 'null']])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('INV-3', $results->first()->reference);
    }

    public function test_date_not_null()
    {
        $results = Invoice::filter(['issued_on' => ['not' => 'null']])->get();

        $this->assertCount(2, $results);
    }

    public function test_datetime_with_date_only_value_matches_the_whole_day()
    {
        // A 10-char value ("YYYY-MM-DD") falls back to whereDate(), matching
        // any time within that day.
        $results = Invoice::filter(['paid_at' => '2024-01-05'])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('INV-1', $results->first()->reference);
    }

    public function test_datetime_with_full_value_requires_exact_match()
    {
        $results = Invoice::filter(['paid_at' => '2024-01-05 10:00:00'])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('INV-1', $results->first()->reference);

        $noMatch = Invoice::filter(['paid_at' => '2024-01-05 11:00:00'])->get();

        $this->assertCount(0, $noMatch);
    }

    public function test_datetime_greater_than_or_equal()
    {
        $results = Invoice::filter(['paid_at' => ['>=' => '2024-02-01 00:00:00']])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('INV-2', $results->first()->reference);
    }

    public function test_datetime_is_null()
    {
        $results = Invoice::filter(['paid_at' => ['is' => 'null']])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('INV-3', $results->first()->reference);
    }
}

class Invoice extends Model
{
    use Filterable;

    protected $fillable = ['reference', 'issued_on', 'paid_at'];

    protected $filterable = [
        'reference' => 'string',
        'issued_on' => 'date',
        'paid_at' => 'datetime',
    ];
}
