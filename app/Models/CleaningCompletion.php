<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A task DONE for a store in one period. Only "done" rows exist; "pending" and
 * "overdue" are derived on read (see CleaningDueService).
 */
class CleaningCompletion extends Model
{
    protected $fillable = [
        'cleaning_task_id',
        'store_id',
        'period_start',
        'period_end',
        'completed_at',
        'completed_by_user_id',
        'note',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end'   => 'date',
        'completed_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(CleaningTask::class, 'cleaning_task_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'cleaning_completion_employees')->withTimestamps();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CleaningCompletionAttachment::class);
    }
}
