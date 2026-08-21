<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evaluation extends Model
{
    protected $fillable = [
        'store_id',
        'period_type',
        'period_key',
        'created_by',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function itemValues(): HasMany
    {
        return $this->hasMany(EvaluationItemValue::class);
    }

    public function chartVerdicts(): HasMany
    {
        return $this->hasMany(EvaluationChartVerdict::class);
    }
}
