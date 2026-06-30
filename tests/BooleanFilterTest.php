<?php

namespace Firevel\Filterable\Tests;

use Firevel\Filterable\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class BooleanFilterTest extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('plan');
            $table->boolean('is_active')->nullable();
            $table->timestamps();
        });

        Subscription::create(['plan' => 'pro', 'is_active' => true]);
        Subscription::create(['plan' => 'basic', 'is_active' => false]);
        Subscription::create(['plan' => 'trial', 'is_active' => null]);
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

    /**
     * @dataProvider truthyValues
     */
    public function test_truthy_values_match_active_subscription($value)
    {
        $results = Subscription::filter(['is_active' => ['=' => $value]])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('pro', $results->first()->plan);
    }

    public static function truthyValues()
    {
        return [
            'boolean true' => [true],
            'string "true"' => ['true'],
            'string "TRUE"' => ['TRUE'],
            'string "1"' => ['1'],
            'int 1' => [1],
        ];
    }

    /**
     * @dataProvider falsyValues
     */
    public function test_falsy_values_match_inactive_subscription($value)
    {
        $results = Subscription::filter(['is_active' => ['=' => $value]])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('basic', $results->first()->plan);
    }

    public static function falsyValues()
    {
        return [
            'boolean false' => [false],
            'string "false"' => ['false'],
            'string "FALSE"' => ['FALSE'],
            'string "0"' => ['0'],
            'int 0' => [0],
        ];
    }

    public function test_default_operator_without_explicit_operator_key()
    {
        $results = Subscription::filter(['is_active' => 'true'])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('pro', $results->first()->plan);
    }

    public function test_is_null_operator()
    {
        $results = Subscription::filter(['is_active' => ['is' => 'null']])->get();

        $this->assertCount(1, $results);
        $this->assertEquals('trial', $results->first()->plan);
    }

    public function test_not_null_operator()
    {
        $results = Subscription::filter(['is_active' => ['not' => 'null']])->get();

        $this->assertCount(2, $results);
    }
}

class Subscription extends Model
{
    use Filterable;

    protected $fillable = ['plan', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $filterable = [
        'plan' => 'string',
        'is_active' => 'boolean',
    ];
}
