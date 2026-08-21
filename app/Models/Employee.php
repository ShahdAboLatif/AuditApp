<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Replicated from HiringPizza (hiring.v1.employee.*). Same shape as the inventory
 * project. PK = the hiring-system employee id.
 */
class Employee extends Model
{
    public $incrementing = false;
    protected $keyType   = 'integer';

    protected $fillable = [
        'id', 'first_name', 'middle_name', 'last_name', 'store_id', 'active',
    ];

    protected function casts(): array
    {
        return [
            'active'   => 'boolean',
            'store_id' => 'integer',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }
}
