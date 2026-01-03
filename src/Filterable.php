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
     * Check if columns are supported and throw exception if not.
     *
     * @var boolean
     */
    protected $validateColumns = false;

    /**
     * Supported operators.
     *
     * @var array
     */
    protected $allowedFilterOperators = [
        '<>' => ['integer', 'id', 'float', 'string'],
        'ne' => ['integer', 'id', 'float', 'string'],
        '>=' => ['integer', 'date', 'datetime', 'id', 'float', 'relationship'],
        'gte' => ['integer', 'date', 'datetime', 'id', 'float', 'relationship'],
        '<=' => ['integer', 'date', 'datetime', 'id', 'float', 'relationship'],
        'lte' => ['integer', 'date', 'datetime', 'id', 'float', 'relationship'],
        '>'  => ['integer', 'date', 'datetime', 'id', 'float', 'relationship'],
        'gt'  => ['integer', 'date', 'datetime', 'id', 'float', 'relationship'],
        '<'  => ['integer', 'date', 'datetime', 'id', 'float', 'relationship'],
        'lt'  => ['integer', 'date', 'datetime', 'id', 'float', 'relationship'],
        '='  => ['integer', 'date', 'datetime', 'id', 'float', 'string', 'relationship', 'boolean', 'json', 'array'],
        'eq'  => ['integer', 'date', 'datetime', 'id', 'float', 'string', 'relationship', 'boolean', 'json', 'array'],
        'like' => ['string'],
        'in'   => ['integer', 'id', 'float', 'string', 'json', 'array'],
        'is'   => ['integer', 'date', 'datetime', 'id', 'float', 'string', 'relationship', 'boolean', 'json', 'array'],
        'not'  => ['integer', 'date', 'datetime', 'id', 'float', 'string', 'relationship', 'boolean', 'json', 'array'],
    ];

    /**
     * Operator aliases mapping.
     *
     * @var array
     */
    protected $operatorAliases = [
        'gt' => '>',
        'gte' => '>=',
        'lt' => '<',
        'lte' => '<=',
        'ne' => '<>',
        'eq' => '=',
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
     * @param  array  $filters   Filters formatted key => val or key => [operator => value]
     * @param  Builder  $query
     * @return Builder
     */
    public function applyFiltersToQuery($filters, $query)
    {
        if (empty($this->filterable) || empty($filters)) {
            return $query;
        }

        if ($this->validateColumns) {
            foreach ($filters as $filterName => $filterValue) {
                $baseColumn = explode('->', $filterName)[0];
                if (! array_key_exists($baseColumn, $this->filterable)) {
                    throw new \Exception("Filter column '$baseColumn' is not allowed.");
                }
            }
        }

        foreach ($filters as $filterName => $filterValue) {
            $baseColumn = explode('->', $filterName)[0];

            // Skip any filters not explicitly declared in $filterable
            if (! array_key_exists($baseColumn, $this->filterable)) {
                continue;
            }

            $filterType = $this->filterable[$baseColumn];

            // If the filterType is "scope", call the local scope method
            if ($filterType === 'scope') {
                $studlyName = Str::studly($filterName);
                $prefixedScopeMethod = 'scopeFilter' . $studlyName;
                $simpleScopeMethod = 'scope' . $studlyName;

                // Prefer scopeFilter{Name} to avoid conflicts with reserved method names
                // Fall back to scope{Name} for backward compatibility
                if (method_exists($this, $prefixedScopeMethod)) {
                    $query->{'filter' . $studlyName}($filters[$filterName], $filters);
                } elseif (method_exists($this, $simpleScopeMethod)) {
                    $query->{Str::camel($filterName)}($filters[$filterName], $filters);
                } else {
                    throw new \Exception("Scope method '$prefixedScopeMethod' or '$simpleScopeMethod' not found on model.");
                }
                continue;
            }

            // If the incoming value is an array like ['>' => '2023-01-01'], loop through operators
            if (is_array($filterValue)) {
                foreach ($filterValue as $operator => $value) {
                    $operator = urldecode($operator);

                    if (! array_key_exists($operator, $this->allowedFilterOperators)) {
                        throw new \Exception('Illegal operator ' . $operator);
                    }
                    if (! in_array($filterType, $this->allowedFilterOperators[$operator])) {
                        throw new \Exception("Operator '$operator' is not allowed for type '$filterType'");
                    }

                    $this->applyFilterToQuery($filterType, $filterName, $value, $operator, $query);
                }
            } else {
                // Default operator (“=”) if none specified
                $this->applyFilterToQuery($filterType, $filterName, $filterValue, null, $query);
            }
        }

        return $query;
    }

    /**
     * Apply a single filter to the query builder.
     *
     * @param  string        $filterType
     * @param  string        $filterName
     * @param  mixed         $filterValue
     * @param  string|null   $operator
     * @param  Builder       $query
     *
     * @return Builder
     */
    public function applyFilterToQuery($filterType, $filterName, $filterValue, $operator, $query)
    {
        $filterRelationshipQuery = $this->filterRelationshipQuery;

        // If no operator was provided, use default "="
        if (empty($operator)) {
            $operator = $this->defaultFilterOperator;
        }

        // Convert operator alias to actual operator if it's an alias
        if (isset($this->operatorAliases[$operator])) {
            $operator = $this->operatorAliases[$operator];
        }

        // Support “relationship.column” notation (only one level deep)
        if (strpos($filterName, '.') !== false) {
            if (substr_count($filterName, '.') > 1) {
                throw new \Exception('Maximum one‐level sub‐query filtering supported.');
            }
            list($relationship, $filterName) = explode('.', $filterName);
        }

        // Handle "in" operator (skip if relationship - handled in whereHas closure)
        if ($operator === 'in' && empty($relationship)) {
            if (is_array($filterValue)) {
                $values = $filterValue;
            } else {
                $values = explode(',', $filterValue);
            }

            // For array type, use whereJsonContains (check if JSON array contains value)
            if ($filterType === 'array') {
                if (count($values) === 1) {
                    return $query->whereJsonContains($filterName, trim($values[0]));
                }

                // Multiple values - check if JSON array contains ANY of them
                return $query->where(function ($q) use ($filterName, $values) {
                    foreach ($values as $value) {
                        $q->orWhereJsonContains($filterName, trim($value));
                    }
                });
            }

            return $query->whereIn($filterName, $values);
        }

        // Handle NULL checks for “is null” / “is not null”
        if (
            ($operator === 'is' || $operator === 'not')
            && strtolower($filterValue) === 'null'
        ) {
            if ($operator === 'is') {
                return $query->whereNull($filterName);
            } else {
                return $query->whereNotNull($filterName);
            }
        }

        // Handle JSON‐path filtering (e.g. "meta->key")
        if (strpos($filterName, '->') !== false) {
            list($filterName, $jsonPath) = explode('->', $filterName, 2);
        }

        // Decide which "where…" method to call based on filterType
        switch ($filterType) {
            case 'id':
            case 'integer':
            case 'float':
            case 'string':
            case 'json':
                $method = 'where';
                break;

            case 'array':
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
                // If YYYY-MM-DD (length 10), use whereDate; otherwise use full “where”
                if (strlen($filterValue) === 10) {
                    $method = 'whereDate';
                } else {
                    $method = 'where';
                }
                break;

            default:
                throw new \Exception('Unsupported filter type ' . $filterType);
        }

        // If this was a "relationship.column" filter
        if (! empty($relationship)) {
            return $query->whereHas(
                Str::camel($relationship),
                function ($query) use ($method, $filterName, $operator, $filterValue, $filterRelationshipQuery, $filterType) {
                    if (! empty($filterRelationshipQuery)) {
                        $query->where($filterRelationshipQuery);
                    }

                    // Handle "in" operator inside relationship
                    if ($operator === 'in') {
                        if (is_array($filterValue)) {
                            $values = $filterValue;
                        } else {
                            $values = explode(',', $filterValue);
                        }

                        // For array type, use whereJsonContains
                        if ($filterType === 'array') {
                            if (count($values) === 1) {
                                return $query->whereJsonContains($filterName, trim($values[0]));
                            }

                            return $query->where(function ($q) use ($filterName, $values) {
                                foreach ($values as $value) {
                                    $q->orWhereJsonContains($filterName, trim($value));
                                }
                            });
                        }

                        return $query->whereIn($filterName, $values);
                    }

                    return $query->$method($filterName, $operator, $filterValue);
                }
            );
        }

        // If type is “relationship” and we had a $filterRelationshipQuery, use that
        if ($filterType === 'relationship' && ! empty($filterRelationshipQuery)) {
            return $query->$method(
                $filterName,
                $operator,
                $filterValue,
                'AND',
                $this->filterRelationshipQuery
            );
        }

        // If we had a JSON path (meta->key), tack it on now
        if (! empty($jsonPath)) {
            return $query->$method("$filterName->$jsonPath", $operator, $filterValue);
        }

        // Default case: normal WHERE clause
        return $query->$method($filterName, $operator, $filterValue);
    }

    /**
     * Allow chaining an extra query on relationships.
     *
     * @param  Builder  $query
     * @return $this
     */
    public function useRelationshipQuery($query)
    {
        $this->filterRelationshipQuery = $query;
        return $this;
    }

    /**
     * Scope a query to apply the given filters.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array  $filters
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
