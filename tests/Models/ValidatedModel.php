<?php

namespace Firevel\Filterable\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Firevel\Filterable\Filterable;

class ValidatedModel extends Model
{
    use Filterable;

    protected $table = 'test_models';

    protected $fillable = ['name', 'email'];

    protected $filterable = [
        'name' => 'string',
        'email' => 'string',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->validateColumns = true;
    }
}