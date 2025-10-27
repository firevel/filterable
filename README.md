# Laravel Filterable

A lightweight trait for Laravel Eloquent models that makes it easy to build dynamic, type‐safe filters on your queries. Instead of hard‐coding dozens of scopes or query clauses, simply declare which fields are “filterable” and let the trait handle operators, casting, and relationship logic for you.

---

## Table of Contents

1. [Installation](#installation)  
2. [Quick Start](#quick-start)  
3. [Configuration](#configuration)  
   - [Defining `$filterable`](#defining-filterable)  
   - [Allowed Filter Types](#allowed-filter-types)  
   - [Supported Operators](#supported-operators)  
   - [Validating Columns](#validating-columns)  
4. [Basic Usage](#basic-usage)  
   - [Filtering by Single Field](#filtering-by-single-field)  
   - [Filtering by Multiple Fields](#filtering-by-multiple-fields)  
   - [Composite (“Virtual”) Filters](#composite-virtual-filters)  
5. [Advanced Filters](#advanced-filters)  
   - [Filtering JSON Columns](#filtering-json-columns)  
   - [Filtering Relationships](#filtering-relationships)  
   - [Boolean & Null Checks](#boolean--null-checks)  
6. [Examples](#examples)  
7. [Tips & Best Practices](#tips--best-practices)  
8. [Troubleshooting](#troubleshooting)  

---

## Installation

Install via Composer:

```bash
composer require firevel/filterable
```

Once installed, there are no service‐provider registrations or config publishes required. The trait is ready to use.

---

## Quick Start

1. Add the `Filterable` trait to your Eloquent model.  
2. Define a protected `$filterable` array, mapping each filter key to its type.  
3. Call the `filter([...])` scope on your queries.

```php
// In app/Models/User.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Firevel\Filterable\Filterable;

class User extends Model
{
    use Filterable;

    /**
     * Specify which fields (or “virtual” keys) can be filtered,
     * along with their data types.
     */
    protected $filterable = [
        'id'         => 'id',
        'first_name' => 'string',
        'last_name'  => 'string',
        'email'      => 'string',
        'created_at' => 'datetime',
    ];
}
```

Now you can do:

```php
$users = User::filter([
    'first_name' => ['like' => 'Smith'],
    'created_at' => ['>'    => '2023-01-01'],
])->get();
```

––

## Configuration

### Defining `$filterable`

In each model that uses the trait, declare a protected `$filterable` array. The **keys** are the names (or aliases) you wish to filter on, and the **values** specify the field’s type. For example:

```php
protected $filterable = [
    'id'            => 'id',
    'first_name'    => 'string',
    'last_name'     => 'string',
    'email'         => 'string',
    'created_at'    => 'datetime',
    'is_active'     => 'boolean',
    'meta'          => 'json',
    'roles'         => 'relationship',
];
```

- If a key corresponds to an actual database column, use its column name.  
- If you want “virtual” filters (e.g. `full_name` that searches both `first_name` and `last_name`), see the [Composite (“Virtual”) Filters](#composite-virtual-filters) section.  

The trait will only apply filters for keys explicitly declared in `$filterable`; any others are ignored by default (or throw an exception if you enable column validation).

---

### Allowed Filter Types

| Type          | Description                                                                           |
| ------------- | ------------------------------------------------------------------------------------- |
| `integer`     | Integer columns or numeric IDs                                                         |
| `id`          | Shorthand for integer when representing a primary/foreign key                           |
| `string`      | Text columns; used with operators like `like`, `=`, `<>`                                |
| `date`        | Date‐only filters (YYYY‐MM‐DD). Under the hood, uses `whereDate()`                      |
| `datetime`    | Date & time filters (YYYY‐MM‐DD HH:MM:SS). Uses `whereDate()` if value is 10 chars long |
| `boolean`     | Casts “true”/“false” (case‐insensitive) to boolean                                      |
| `json`        | JSON columns; used with `where()` or JSON operators                                      |
| `array`       | JSON columns containing arrays; uses `whereJsonContains()`                               |
| `relationship`| Expect a related model or “has” filter on a `belongsTo` / `hasMany` style relationship    |

---

### Supported Operators

By default, the trait allows the following operators for each filter type. To override operators on a field, simply pass an associative array (`'[ operator ] => [ value ]'`).

| Operator | Alias | Meaning                                    | Allowed Types                                     |
| -------- | ----- | ------------------------------------------ | ------------------------------------------------- |
| `=`      | `eq`  | Equal to (default if no operator provided) | `integer`, `id`, `string`, `date`, `datetime`, `relationship`, `boolean`, `json`, `array` |
| `<>`     | `ne`  | Not equal to                               | `integer`, `id`, `string`                         |
| `>`      | `gt`  | Greater than                               | `integer`, `date`, `datetime`, `id`, `relationship`|
| `>=`     | `gte` | Greater than or equal                      | `integer`, `date`, `datetime`, `id`, `relationship`|
| `<`      | `lt`  | Less than                                  | `integer`, `date`, `datetime`, `id`, `relationship`|
| `<=`     | `lte` | Less than or equal                         | `integer`, `date`, `datetime`, `id`, `relationship`|
| `like`   | -     | SQL LIKE (for partial string matches)       | `string`                                          |
| `in`     | -     | SQL IN (for lists or comma‐separated values)| `integer`, `id`, `string`, `json`                 |
| `is`     | -     | IS NULL check (pass `'null'` as value)      | `integer`, `date`, `datetime`, `id`, `string`, `boolean`, `json`, `array` |
| `not`    | -     | IS NOT NULL (pass `'null'` as value)        | `integer`, `date`, `datetime`, `id`, `string`, `boolean`, `json`, `array` |

> **Note**: If you supply a plain scalar (e.g. `'foo'`) instead of `['=' => 'foo']`, the trait assumes the `=` operator by default.

#### Operator Aliases

To avoid using special characters in URLs, you can use text-based aliases for comparison operators:

- `gt` for `>` (greater than)
- `gte` for `>=` (greater than or equal)
- `lt` for `<` (less than)
- `lte` for `<=` (less than or equal)
- `ne` for `<>` (not equal)
- `eq` for `=` (equal)

These aliases work exactly the same as their symbolic counterparts:

```php
// Using symbolic operators
$users = User::filter([ 'age' => ['>' => 25] ])->get();

// Using alias operators (URL-friendly)
$users = User::filter([ 'age' => ['gt' => 25] ])->get();

// Both produce: SELECT * FROM users WHERE age > 25
```

---

### Validating Columns

By default, the trait will **ignore** any filters whose key is not in `$filterable`. If you’d rather throw an exception when an unknown filter is passed, enable column validation:

```php
class User extends Model
{
    use Filterable;

    protected $validateColumns = true;

    protected $filterable = [
        'id'       => 'id',
        'email'    => 'string',
        'status'   => 'string',
    ];
}
```

With `$validateColumns = true`, passing `->filter(['not_a_column' => ['=' => 5]])` will throw:
```
Exception: Filter column 'not_a_column' is not allowed.
```

---

## Basic Usage

### Filtering by Single Field

Filter on one attribute by providing a key‐value pair. If you omit the operator, it defaults to `=`.

```php
// 1) Simple equality (defaults to '=')
$users = User::filter([ 'id' => 5 ])->get();
// → SELECT * FROM users WHERE id = 5;

// 2) Explicit operators
$users = User::filter([ 'created_at' => ['>' => '2024-01-01'] ])->get();
// → SELECT * FROM users WHERE created_at > '2024-01-01';

// 3) LIKE operator for strings
$users = User::filter([ 'email' => ['like' => '%@example.com'] ])->get();
// → SELECT * FROM users WHERE email LIKE '%@example.com';
```

### Filtering by Multiple Fields

Combine as many filters as you need; they are joined with `AND` logic:

```php
$filters = [
    'first_name' => ['like' => 'John'],
    'created_at' => ['>='   => '2025-01-01'],
    'status'     => ['='    => 'active'],
];

$users = User::filter($filters)->get();
// → SELECT * FROM users
//    WHERE first_name LIKE '%John%'
//      AND created_at >= '2025-01-01'
//      AND status = 'active';
```

---

### Composite (“Virtual”) Filters

Sometimes you want a single filter key (e.g. `name`) that actually applies to multiple columns (like `first_name` **OR** `last_name`). You can achieve this by declaring a “scope”-type entry in `$filterable` and then adding a local scope method on your model.

#### Example: “name” → searches `first_name` OR `last_name`

1. **Declare a `scope` filter key**  
   In `User.php`:
   ```php
   use Illuminate\Database\Eloquent\Model;
   use Firevel\Filterable\Filterable;

   class User extends Model
   {
       use Filterable;

       protected $filterable = [
           'first_name' => 'string',
           'last_name'  => 'string',
           'email'      => 'string',
           'created_at' => 'datetime',

           // “name” isn’t a real column; mark it as a custom scope
           'name'       => 'scope',
       ];

       // Add a local scopeName() to combine first_name OR last_name
       // The second parameter ($allFilters) provides access to all filters
       public function scopeName($query, $value, $allFilters = [])
       {
           $query->where(function ($q) use ($value) {
               $q->where('first_name', 'like', "%{$value}%")
                 ->orWhere('last_name',  'like', "%{$value}%");
           });
       }
   }
   ```

2. **Use it in your code exactly like any other filter**  
   ```php
   // Will invoke scopeName() internally
   $users = User::filter([
       'name'       => ['like' => 'Smith'], 
       'created_at' => ['>'    => '2025-01-01']
   ])->get();
   ```
   Under the hood, the trait sees `'name' => 'scope'` and calls `$query->name('Smith')`, which in turn applies:
   ```sql
   WHERE (first_name LIKE '%Smith%' OR last_name LIKE '%Smith%')
     AND created_at > '2025-01-01'
   ```

#### Why use a "scope"-type filter?

- **Zero changes to the trait**: the existing code already checks `if ($filterType === 'scope')` and executes the corresponding local scope.
- **Keeps your trait logic simple**: you don't have to override the trait's internal validation or operator parsing—your `scopeName()` takes full responsibility for how the filter behaves.
- **Reusable & readable**: everyone knows that "scopeX" is a local query modifier, and the trait simply defers to it.

#### Accessing Other Filters in Scope Methods

Scope filter methods receive two parameters:
1. **`$value`** - The specific value for this filter
2. **`$allFilters`** - The complete array of all filters being applied

This allows you to create conditional logic based on other filters:

```php
protected $filterable = [
    'search'     => 'scope',
    'category'   => 'string',
    'status'     => 'string',
];

public function scopeSearch($query, $value, $allFilters = [])
{
    $query->where(function ($q) use ($value, $allFilters) {
        $q->where('title', 'like', "%{$value}%")
          ->orWhere('description', 'like', "%{$value}%");

        // Apply different search logic if category filter is present
        if (isset($allFilters['category'])) {
            $q->orWhere('tags', 'like', "%{$value}%");
        }
    });
}
```

---

## Advanced Filters

### Filtering JSON Columns

If you have a JSON column (e.g. `meta`), you can:

- **Filter by exact JSON key‐value**:
  ```php
  protected $filterable = [
      'meta' => 'json',
      // … other fields …
  ];
  ```
  ```php
  // Get users whose JSON “meta->role” equals “admin”
  $users = User::filter([ 'meta->role' => ['=' => 'admin'] ])->get();
  // → SELECT * FROM users WHERE JSON_EXTRACT(meta, '$.role') = 'admin';
  ```

- **Filter by array contents** (for JSON arrays) by using type `array`:
  ```php
  protected $filterable = [
      'tags' => 'array', // assumes tags is a JSON array column
  ];
  ```
  ```php
  // Get users whose “tags” array contains “premium”
  $users = User::filter([ 'tags' => ['in' => 'premium'] ])->get();
  // → SELECT * FROM users WHERE JSON_CONTAINS(tags, '"premium"');
  ```

---

### Filtering Relationships

If you want to filter on related models (e.g. `User` hasMany `Order`), declare the key as `relationship` in `$filterable`. Then pass either:

1. A scalar/array (for simple `has()` checks).  
2. A nested filter array to apply conditions on the related model.

```php
// In User.php
protected $filterable = [
    'email'      => 'string',
    'orders'     => 'relationship',
];

// In Order.php (no special setup required)
class Order extends Model { /* … */ }
```

```php
// 1) Just check that a user has at least one order:
$usersWithAnyOrder = User::filter([ 'orders' => ['>' => 0] ])->get();
// → SELECT * FROM users 
//    WHERE ( SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id ) > 0;

// 2) Filter by a condition on the order itself:
$filters = [
    'orders.status' => ['=' => 'shipped'],
    'email'         => ['like' => '%@example.com'],
];
$users = User::filter($filters)->get();
// → SELECT * FROM users
//    WHERE EXISTS (
//      SELECT 1 FROM orders 
//       WHERE orders.user_id = users.id 
//         AND status = 'shipped'
//    ) 
//      AND email LIKE '%@example.com';
```

> **Tip:** If you need a more complex subquery on the relationship, you can chain `useRelationshipQuery()` before calling `filter()`.  
> ```php
> // Define a custom where clause for the related model
> $relatedWhere = function ($query) {
>     $query->where('price', '>', 100);
> };
>
> User::useRelationshipQuery($relatedWhere)
>     ->filter([ 'orders' => ['in' => [1,2,3]] ])
>     ->get();
> ```

---

### Boolean & Null Checks

- **Boolean**  
  ```php
  protected $filterable = [
      'is_active' => 'boolean',
  ];
  ```
  ```php
  // Accepts true/false, "1"/"0", "true"/"false" (case insensitive)
  $activeUsers   = User::filter(['is_active' => ['=' => 'true']])->get();
  $inactiveUsers = User::filter(['is_active' => ['=' => '0']])->get();
  ```

- **`IS NULL` / `IS NOT NULL`**  
  For any type (`integer`, `string`, `date`, etc.), you can check nulls via `is` or `not` with the literal `'null'`:
  ```php
  // Users with no email
  $usersNoEmail = User::filter([ 'email' => ['is' => 'null'] ])->get();

  // Users where deleted_at IS NOT NULL (soft‐deleted)
  $trashed = User::filter([ 'deleted_at' => ['not' => 'null'] ])->get();
  ```

---

## Examples

Below are a few real‐world scenarios illustrating how you might combine filters.

```php
// 1) Find all “admin” users created in the last 30 days,
//    whose email domain is “example.com” and have placed at least one “shipped” order.

$filters = [
    'role'       => ['='    => 'admin'],
    'created_at' => ['>='   => now()->subDays(30)->toDateString()],
    'email'      => ['like' => '%@example.com'],
    'orders.status' => ['=' => 'shipped'],
];

$admins = User::filter($filters)
    ->orderBy('created_at', 'desc')
    ->paginate(15);


// 2) Search by “full name” (composite filter: first_name OR last_name),
//    and also filter by a JSON metadata key:
$filters = [
    'name'           => ['like' => 'Doe'],         // see “Composite Filters”
    'meta->department'=> ['='  => 'engineering'],  // JSON column
    'status'         => ['='    => 'active'],
];

$users = User::filter($filters)->get();


// 3) Get all products whose “tags” JSON array includes either “sale” or “new”:
$filters = [
    'tags' => ['in' => 'sale,new'],  // comma‐separated or array
];

$productsOnSaleOrNew = Product::filter($filters)->get();
```

---

## Tips & Best Practices

- **Keep `$filterable` up to date**: Every column or relationship you wish to filter on must appear in the array.  
- **Use strict column validation in production**:  
  ```php
  protected $validateColumns = true;
  ```  
  This prevents typos or malicious filters from silently being ignored.  
- **Leverage composite (virtual) filters sparingly**: Only create a custom scope if you truly need to combine two or more columns into one semantic filter.  
- **Avoid leading wildcards unless necessary**:  
  - `LIKE '%foo%'` is flexible but slow on large tables. Whenever possible, use `LIKE 'foo%'` or full‐text search.  
- **Paginate filtered results**: Filtering can return large result sets. Always pair with `→paginate()` or `→simplePaginate()` to avoid memory issues.  
- **Test your JSON and relationship filters** thoroughly—wrong syntax or missing indexes can lead to unexpected results or performance hits.  

---

## Troubleshooting

- **“Filter column ‘xyz’ is not allowed.”**  
  You enabled `protected $validateColumns = true` and passed a key not in `$filterable`. Either add it to the array or disable validation.

- **`Operator ‘in’ is not allowed for type ‘integer’`**  
  Check your `$filterable` type for that key. The `in` operator only works on `integer`, `id`, `string`, or `json`—not on `date/datetime` out of the box.

- **Composite filter not working**  
  If you declared a key as `'scope'` in `$filterable` (for example, `'name' => 'scope'`), make sure you have a corresponding `scopeName()` method on the model. If the trait can’t find `scopeName`, it will skip your filter.

- **Slow queries on large tables**  
  - Check if you’re using `%…%` wildcards (leading `%`) on very large text columns—those can’t use indexes.  
  - Consider adding a full‐text index for complex search scenarios or switch to a dedicated search engine (Scout, Algolia, MeiliSearch).

---

With this simple trait, you can keep your controllers and repositories neat, DRY, and expressive—no more copy/pasting dozens of `if ($request->has('…')) { … }` checks. Happy filtering!
