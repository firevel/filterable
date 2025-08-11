<?php

namespace Firevel\Filterable\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Firevel\Filterable\Filterable;

class Post extends Model
{
    use Filterable;

    protected $fillable = ['title', 'content', 'user_id', 'meta'];

    protected $casts = [
        'meta' => 'json',
    ];

    protected $filterable = [
        'id' => 'id',
        'title' => 'string',
        'user_id' => 'integer',
        'meta' => 'json',
        'user' => 'relationship',
        'comments' => 'relationship',
        // Explicit sub-resources for relationship filtering
        'user.name' => 'string',
        'user.email' => 'string',
        'user.level' => 'integer',
        'comments.user_id' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
}
