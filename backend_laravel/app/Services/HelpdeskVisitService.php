<?php

namespace App\Services;

use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

// Keep business operations and data access separate from HTTP responses.
class HelpdeskVisitService
{
    public function pendingVisits(User $user): Collection
    {

        abort_unless($user->role === 'helpdesk', 403);

        $visits = Visit::where('status', 'pending')
            ->with(['user.company', 'medications'])
            ->orderBy('created_at')
            ->get();

        return $visits;
    }

    public function review(User $user, int $visitId, array $data): Visit
    {

        abort_unless($user->role === 'helpdesk', 403);

        $visit = DB::transaction(function () use ($visitId, $data, $user) {
            $visit = Visit::where('id', $visitId)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $visit->status === 'pending',
                409,
                'This visit has already been processed.'
            );

            abort_if(
                $visit->user_id === null,
                422,
                'This visit must belong to an employee.'
            );

            // Save the decision and its audit fields together.
            // Reviewer identity and time come from the server, not request input.
            $visit->status = $data['status'];
            $visit->reviewed_by = $user->id;
            $visit->reviewed_at = now();
            $visit->save();

            return $visit;
        });

        return $visit;
    }
}
