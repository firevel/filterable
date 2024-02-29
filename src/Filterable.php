<?php

namespace Firevel\Filterable;

use Illuminate\Support\Str;

trait Filterable
{
    /**
     * Default operator if no other operator detected.
     *
     * @var string
     */
    protected $defaultFilterOperator = '=';

    /**
     * Supported operators.
     *
     * @var array
     */
    protected $allowedFilterOperators = [
        '>=' => ['integer', 'date', 'datetime', 'id', 'relationship'],
        '<=' => ['integer', 'date', 'datetime', 'id', 'relationship'],
        '>' => ['integer', 'date', 'datetime', 'id', 'relationship'],
        '<' => ['integer', 'date', 'datetime', 'id', 'relationship'],
        '=' => ['integer', 'date', 'datetime', 'id', 'string', 'relationship', 'boolean'],
        'like' => ['string'],
        'in' => ['integer', 'id', 'string'],
    ];

    /**
     * Extra query used in relationship filters.
     *
     * @var null|Closure
     */
    protected $filterRelationshipQuery;

    /**
     * Apply filters to query.
     *
     * @param  array  $filters  Filters formatted key => val or key => [operator => vale]
     * @param  Builder  $query
     * @return Builder
     */
    public function applyFiltersToQuery($filters, $query)
    {
        if (empty($this->filterable) || empty($filters)) {
            return $query;
        }

        $filterable = array_intersect_key($this->filterable, $filters);

        foreach ($filterable as $filterName => $filterType) {
            if ($filterType === 'scope') {
                $query->{Str::camel($filterName)}($filters[$filterName]);
                continue;
            }
            if (is_array($filters[$filterName])) {
                foreach ($filters[$filterName] as $operator => $filterValue) {
                    $operator = urldecode($operator);
                    if (! array_key_exists($operator, $this->allowedFilterOperators)) {
                        throw new \Exception('Illegal operator '.$operator);
                    }
                    if (! in_array($filterType, $this->allowedFilterOperators[$operator])) {
                        throw new \Exception('Operator '.$operator.' is not allowed for ' . $filterType);
                    }
                    $this->applyFilterToQuery($filterType, $filterName, $filterValue, $operator, $query);
                }
            } else {
                $this->applyFilterToQuery($filterType, $filterName, $filters[$filterName], null, $query);
            }
        }
    }

    /**
     * Apply filter to query.
     *
     * @param  string  $filterType  Type
     * @param  string  $filterName  Name
     * @param  string  $filterValue  Value
     * @param  string|null  $operator  Operator ex.: >=
     * @param  Builder  $query
     *
     * @return Builder
     */
    public function applyFilterToQuery($filterType, $filterName, $filterValue, $operator, $query)
    {
        $filterRelationshipQuery = $this->filterRelationshipQuery;
        if (empty($operator)) {
            $operator = $this->defaultFilterOperator;
        }

        if (strpos($filterName, '.')) {
            if (substr_count($filterName, '.')>1) {
                throw new \Exception('Maximum one level sub-query filtering supported.');
            }
            [$relationship, $filterName] = explode('.', $filterName);
        }

        // WHERE IN operator handling
        if ($operator == 'in') {
            if (is_array($filterValue)) {
                $array = $filterValue;
            } else {
                $array = explode(',', $filterValue);
            }
            return $query->whereIn($filterName, $array);
        }

        switch ($filterType) {
            case 'id':
            case 'integer':
            case 'string':
                $method = 'where';
                break;
            case 'relationship':
                $filterName = Str::camel($filterName);
                $method = 'has';
                break;
            case 'boolean':
                $filterValue = filter_var($filterValue, FILTER_VALIDATE_BOOLEAN);
                $method = 'where';
                break;
            case 'date':
                $method = 'whereDate';
                break;
            case 'datetime':
                if (strlen($filterValue) == 10) {
                    $method = 'whereDate';
                }
                $method = 'where';
                break;
            default:
                throw new \Exception('Unsupported filter type '.$filterType);
        }

        if (!empty($relationship)) {
            return $query->whereHas(
                Str::camel($relationship),
                function($query) use($method, $filterName, $operator, $filterValue, $filterRelationshipQuery) {
                    if (! empty($filterRelationshipQuery)) {
                        $query->where($filterRelationshipQuery);                        
                    }
                    $query->$method($filterName, $operator, $filterValue);
                }
            );
        }

        if ($filterType == 'relationship'  && ! empty($filterRelationshipQuery)) {
            return $query->$method($filterName, $operator, $filterValue, 'AND', $this->filterRelationshipQuery);
        }

        return $query->$method($filterName, $operator, $filterValue);
    }

    /**
     * Use extra query on relationship queries.
     * 
     * @param  Builder $query
     * @return Builder
     */
    public function useRelationshipQuery($query)
    {
        $this->filterRelationshipQuery = $query;

        return $this;
    }

    /**
     * Scope a query to apply filters.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilter($query, $filters)
    {
        if (empty($filters)) {
            return $query;
        }

        $this->applyFiltersToQuery($filters, $query);

        return $query;
    }
}
