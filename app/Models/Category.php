<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'label',
    ];

    public function entities(): HasMany
    {
        return $this->hasMany(Entity::class);
    }
}
