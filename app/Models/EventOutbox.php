<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventOutbox extends Model
{
    protected $table = 'event_outbox';

    protected $fillable = [
        'event_id',
        'subject',
        'payload',
        'published_at',
        'attempts',
        'last_error',
    ];

    protected $casts = [
        'payload'      => 'array',
        'published_at' => 'datetime',
        'attempts'     => 'integer',
    ];
}
