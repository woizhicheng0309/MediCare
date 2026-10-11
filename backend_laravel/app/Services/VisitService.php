<?php

namespace App\Services;

use App\Models\Medication;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Keep business operations and data access separate from HTTP responses.
class VisitService
{
    public function ownVisits(User $user): Collection
    {

        $visits = $user->visits()
            ->with('medications')
            ->orderBy('visited_on', 'desc')
            ->get();

        return $visits;
    }

    public function submit(User $user, array $data): Visit
    {

        // Only employees may submit their own clinic visits.
        abort_unless($user->role === 'employee', 403);

        $medications = $data['medications'] ?? [];

        // Medication coverage cannot exceed the total coverage of the visit.
        $medicationTotal = array_sum(array_column($medications, 'covered_amount'));

        if ($medicationTotal > (int) $data['covered_amount']) {
            throw ValidationException::withMessages([
                'medications' => [
                    'Medication covered amounts cannot exceed the visit covered amount.',
                ],
            ]);
        }

        // Save the visit and all medications together, or roll back every write.
        $visit = DB::transaction(function () use ($user, $data, $medications) {
            $visit = new Visit;
            $visit->clinic = $data['clinic'];
            $visit->visited_on = $data['visited_on'];
            $visit->covered_amount_cents = (int) $data['covered_amount'] * 100;
            $visit->status = 'pending';

            $visit->diagnosis = $data['diagnosis'] ?? null;
            $visit->visited_time = $data['visited_time'] ?? null;
            $visit->doctor_name = $data['doctor_name'] ?? null;
            $visit->doctor_title = $data['doctor_title'] ?? null;
            $visit->sick_leave_started_on = $data['sick_leave_started_on'] ?? null;
            $visit->sick_leave_ended_on = $data['sick_leave_ended_on'] ?? null;

            // Assign ownership from the authenticated employee.
            $user->visits()->save($visit);

            foreach ($medications as $item) {
                $medication = new Medication;
                $medication->name = $item['name'];
                $medication->dosage = $item['dosage'] ?? null;
                $medication->instructions = $item['instructions'] ?? null;
                $medication->covered_amount_cents = (int) $item['covered_amount'] * 100;

                // Set visit_id through the relationship, never from client input.
                $visit->medications()->save($medication);
            }

            return $visit;
        });

        return $visit->load('medications');
    }
}
