<?php

namespace Firevel\Filterable\Tests;

use Firevel\Filterable\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class NullCheckFilterTest extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->integer('manager_id')->nullable();
            $table->boolean('is_remote')->nullable();
            $table->date('termination_date')->nullable();
            $table->timestamps();
        });

        Employee::create(['name' => 'Active With Manager', 'phone' => '555-0001', 'manager_id' => 1, 'is_remote' => true, 'termination_date' => null]);
        Employee::create(['name' => 'No Phone No Manager', 'phone' => null, 'manager_id' => null, 'is_remote' => null, 'termination_date' => null]);
        Employee::create(['name' => 'Terminated', 'phone' => '555-0003', 'manager_id' => 1, 'is_remote' => false, 'termination_date' => '2024-05-01']);
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

    public function test_is_null_on_string_column()
    {
        $results = Employee::filter(['phone' => ['is' => 'null']])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('No Phone No Manager', $results->first()->name);
    }

    public function test_not_null_on_string_column()
    {
        $results = Employee::filter(['phone' => ['not' => 'null']])->get();

        $this->assertCount(2, $results);
    }

    public function test_is_null_on_integer_column()
    {
        $results = Employee::filter(['manager_id' => ['is' => 'null']])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('No Phone No Manager', $results->first()->name);
    }

    public function test_not_null_on_integer_column()
    {
        $results = Employee::filter(['manager_id' => ['not' => 'null']])->get();

        $this->assertCount(2, $results);
    }

    public function test_is_null_on_boolean_column()
    {
        $results = Employee::filter(['is_remote' => ['is' => 'null']])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('No Phone No Manager', $results->first()->name);
    }

    public function test_is_null_on_date_column()
    {
        $results = Employee::filter(['termination_date' => ['is' => 'null']])->get();

        $this->assertCount(2, $results);
    }

    public function test_not_null_on_date_column_finds_terminated_employee()
    {
        $results = Employee::filter(['termination_date' => ['not' => 'null']])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Terminated', $results->first()->name);
    }

    public function test_null_check_is_case_insensitive()
    {
        $results = Employee::filter(['phone' => ['is' => 'NULL']])->get();

        $this->assertCount(1, $results);
    }

    public function test_combining_null_check_with_another_filter()
    {
        $results = Employee::filter([
            'manager_id' => ['not' => 'null'],
            'is_remote' => false,
        ])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Terminated', $results->first()->name);
    }
}

class Employee extends Model
{
    use Filterable;

    protected $fillable = ['name', 'phone', 'manager_id', 'is_remote', 'termination_date'];

    protected $casts = [
        'is_remote' => 'boolean',
    ];

    protected $filterable = [
        'name' => 'string',
        'phone' => 'string',
        'manager_id' => 'integer',
        'is_remote' => 'boolean',
        'termination_date' => 'date',
    ];
}
