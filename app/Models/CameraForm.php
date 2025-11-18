<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CameraForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'entity_id',
        'audit_id',
        'rating_id',
        'note',
    ];

    /**
     * Get the user that owns the camera form.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the entity that owns the camera form.
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /**
     * Get the audit that owns the camera form.
     */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    /**
     * Get the rating that owns the camera form.
     */
    public function rating(): BelongsTo
    {
        return $this->belongsTo(Rating::class);
    }
}
