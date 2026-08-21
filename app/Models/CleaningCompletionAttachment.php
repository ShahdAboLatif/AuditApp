<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CleaningCompletionAttachment extends Model
{
    protected $fillable = [
        'cleaning_completion_id',
        'path',
    ];

    public function completion(): BelongsTo
    {
        return $this->belongsTo(CleaningCompletion::class, 'cleaning_completion_id');
    }
}
