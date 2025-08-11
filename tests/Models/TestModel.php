<?php

namespace Firevel\Filterable\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Firevel\Filterable\Filterable;

class TestModel extends Model
{
    use Filterable;

    protected $fillable = [
        'name',
        'email',
        'age',
        'price',
        'active',
        'birth_date',
        'settings',
        'tags',
        'user_id',
    ];

    protected $casts = [
        'active' => 'boolean',
        'settings' => 'json',
        'tags' => 'json',
        'birth_date' => 'date',
    ];

    protected $filterable = [
        'id' => 'id',
        'name' => 'string',
        'email' => 'string',
        'age' => 'integer',
        'price' => 'integer',
        'active' => 'boolean',
        'birth_date' => 'date',
        'created_at' => 'datetime',
        'settings' => 'json',
        'tags' => 'array',
        'user' => 'relationship',
        // Explicit sub-resources for relationship filtering
        'user.email' => 'string',
        'user.name' => 'string',
        'user.level' => 'integer',
        'activeUsers' => 'scope',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActiveUsers($query, $value)
    {
        if ($value) {
            return $query->where('active', true)
                        ->whereHas('user', function ($q) {
                            $q->where('level', '>', 5);
                        });
        }
        return $query;
    }
}
