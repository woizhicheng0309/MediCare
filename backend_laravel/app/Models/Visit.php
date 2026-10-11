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

    // Keep leave dates as YYYY-MM-DD in API responses, without a time component.
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'sick_leave_started_on' => 'date:Y-m-d',
            'sick_leave_ended_on' => 'date:Y-m-d',
        ];
    }

    // A visit belongs to its reviewer through the reviewed_by foreign key.
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
