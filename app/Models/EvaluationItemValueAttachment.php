<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationItemValueAttachment extends Model
{
    protected $fillable = [
        'evaluation_item_value_id',
        'path',
    ];

    public function itemValue(): BelongsTo
    {
        return $this->belongsTo(EvaluationItemValue::class, 'evaluation_item_value_id');
    }
}
