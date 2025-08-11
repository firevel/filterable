<?php

namespace Firevel\Filterable\Tests;

use PHPUnit\Framework\TestCase;
use Firevel\Filterable\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Mockery;

class FilterableTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testOperatorAliasesAreSupported()
    {
        $model = new TestModel();
        
        $supportedAliases = [
            'gt' => '>',
            'gte' => '>=',
            'lt' => '<',
            'lte' => '<=',
            'ne' => '<>',
            'eq' => '=',
        ];

        foreach ($supportedAliases as $alias => $expectedOperator) {
            $query = Mockery::mock(Builder::class);
            $query->shouldReceive('where')
                ->once()
                ->with('age', $expectedOperator, 25)
                ->andReturnSelf();

            $model->applyFilterToQuery('integer', 'age', 25, $alias, $query);
        }
        // Mark that expectations were verified
        $this->addToAssertionCount(1);
    }

    public function testOperatorAliasesInFilterScope()
    {
        $model = new TestModel();
        $query = Mockery::mock(Builder::class);
        
        $query->shouldReceive('where')
            ->once()
            ->with('age', '>', 25)
            ->andReturnSelf();
            
        $query->shouldReceive('where')
            ->once()
            ->with('score', '<', 100)
            ->andReturnSelf();

        $filters = [
            'age' => ['gt' => 25],
            'score' => ['lt' => 100],
        ];

        $model->applyFiltersToQuery($filters, $query);
        $this->addToAssertionCount(1);
    }

    public function testMixedOperatorsAndAliases()
    {
        $model = new TestModel();
        $query = Mockery::mock(Builder::class);
        
        $query->shouldReceive('where')
            ->once()
            ->with('age', '>', 25)
            ->andReturnSelf();
            
        $query->shouldReceive('where')
            ->once()
            ->with('score', '>=', 50)
            ->andReturnSelf();

        $filters = [
            'age' => ['>' => 25],
            'score' => ['gte' => 50],
        ];

        $model->applyFiltersToQuery($filters, $query);
        $this->addToAssertionCount(1);
    }
}

class TestModel extends Model
{
    use Filterable;

    protected $filterable = [
        'age' => 'integer',
        'score' => 'integer',
        'name' => 'string',
        'created_at' => 'date',
    ];
}
