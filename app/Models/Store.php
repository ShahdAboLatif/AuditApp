<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    protected $fillable = [
        'id',
        'store',
        'group',
    ];

    protected $casts = [
        'group' => 'integer',
    ];

    /**
     * Resolve a URL {store_id} value to the internal store id.
     * Mirrors the inventory project's Store::idFromNumber: matches the human
     * store key (the `store` column), falling back to the internal id when the
     * value is the numeric primary key.
     */
    public static function idFromNumber(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = static::query()->where('store', $value)->value('id');
        if ($id !== null) {
            return (int) $id;
        }

        if (ctype_digit($value) && static::query()->whereKey((int) $value)->exists()) {
            return (int) $value;
        }

        return null;
    }

    public function cameraForms(): HasMany
    {
        return $this->hasMany(CameraForm::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(Audit::class);
    }
}
