<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Medication extends Model
{
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
}