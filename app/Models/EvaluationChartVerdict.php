<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationChartVerdict extends Model
{
    protected $fillable = [
        'evaluation_id',
        'cleaning_task_id',
        'frequency',
        'weight',
        'verdict',
        'note',
    ];

    protected $casts = [
        'weight' => 'integer',
    ];

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(CleaningTask::class, 'cleaning_task_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EvaluationChartVerdictAttachment::class);
    }
}
