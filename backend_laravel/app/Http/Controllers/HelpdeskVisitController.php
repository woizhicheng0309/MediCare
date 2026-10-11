<?php

namespace App\Http\Controllers;

use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HelpdeskVisitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user('api');

        abort_unless($user->role === 'helpdesk', 403);

        $visits = Visit::where('status', 'pending')
            ->with(['user.company', 'medications'])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'visits' => $visits,
        ]);
    }

    public function updateStatus(Request $request, int $visitId): JsonResponse
    {
        $user = $request->user('api');

        abort_unless($user->role === 'helpdesk', 403);

        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
        ]);

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

        return response()->json([
            'message' => 'Visit status updated successfully.',
            'visit' => $visit,
        ]);
    }
}