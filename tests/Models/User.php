<?php

namespace Firevel\Filterable\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Firevel\Filterable\Filterable;

class User extends Model
{
    use Filterable;

    protected $fillable = ['name', 'email', 'level'];

    protected $filterable = [
        'id' => 'id',
        'name' => 'string',
        'email' => 'string',
        'level' => 'integer',
        'posts' => 'relationship',
    ];

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function testModels()
    {
        return $this->hasMany(TestModel::class);
    }
}