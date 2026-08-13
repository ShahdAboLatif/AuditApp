<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationChartVerdictAttachment extends Model
{
    protected $fillable = [
        'evaluation_chart_verdict_id',
        'path',
    ];

    public function verdict(): BelongsTo
    {
        return $this->belongsTo(EvaluationChartVerdict::class, 'evaluation_chart_verdict_id');
    }
}
