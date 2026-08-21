<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CleaningTask extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'weight',
        'photo_required',
        // rule
        'frequency',
        'interval',
        'week_days',
        'interval_hours',
        'starts_at',
        'ends_at',
        'due_time',
        'created_by',
    ];

    protected $casts = [
        'weight'         => 'integer',
        'photo_required' => 'boolean',
        'interval'       => 'integer',
        'week_days'      => 'array',
        'interval_hours' => 'integer',
        'starts_at'      => 'date',
        'ends_at'        => 'date',
    ];

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'cleaning_task_stores')->withTimestamps();
    }

    public function completions(): HasMany
    {
        return $this->hasMany(CleaningCompletion::class);
    }

    /**
     * The attachment rule is derived from frequency, not chosen by the user.
     */
    public static function photoRequiredFor(string $frequency): bool
    {
        return $frequency !== 'hourly';
    }
}
