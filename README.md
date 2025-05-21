# Laravel Filterable

A simple trait for Laravel Eloquent models that allows you to easily filter your queries.

## Installation

```bash
composer require firevel/filterable
```

## Setup

1. Add the `Filterable` trait to your Eloquent model.
2. Define the `$filterable` property on your model to specify which attributes can be filtered and their types.

```php
use Firevel\Filterable\Filterable;

class User extends Model
{
    use Filterable;

    protected $filterable = [
        'user_id' => 'id',
        'name' => 'string',
        'created_at' => 'datetime',
        // ... other attributes ...
    ];
}
```

## Allowed Filter Types

- `integer`
- `date`
- `datetime`
- `id`
- `string`
- `relationship`
- `boolean`
- `json`
- `array`

## Supported Operators

- `=` - Equal to (default)
- `<>` - Not equal to
- `>` - Greater than
- `>=` - Greater than or equal to
- `<` - Less than
- `<=` - Less than or equal to
- `like` - SQL LIKE operator (for strings)
- `in` - SQL IN operator (for arrays of values)
- `is` - IS NULL check (use with value 'null')
- `not` - IS NOT NULL check (use with value 'null')

## Usage

Use the `filter()` scope on your Eloquent query to apply filters.

```php
// Fetch users with user_id equal to 5
$users = User::filter(['user_id' => ['=' => 5]])->get();

// Fetch users created after a certain date
$users = User::filter(['created_at' => ['>' => '2023-01-01']])->get();

// Fetch users where email is NULL
$users = User::filter(['email' => ['is' => 'null']])->get();

// ... other filter combinations ...
```
