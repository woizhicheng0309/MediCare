<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Visit extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function medications(): HasMany
    {
        return $this->hasMany(Medication::class);
    }

    // Convert the stored review timestamp into a Carbon date-time object.
    protected function casts(): array // protected function can only called internally or inherited class
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    // A visit belongs to its reviewer through the reviewed_by foreign key.
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
