<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rating extends Model
{
    protected $fillable = [
        'label',
    ];

    public function cameraForms(): HasMany
    {
        return $this->hasMany(CameraForm::class);
    }
}
