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

## Usage

Use the `filter()` scope on your Eloquent query to apply filters.

```php
// Fetch users with user_id equal to 5
$users = User::filter(['user_id' => ['=' => 5]])->get();

// Fetch users created after a certain date
$users = User::filter(['created_at' => ['>' => '2023-01-01']])->get();

// ... other filter combinations ...
```