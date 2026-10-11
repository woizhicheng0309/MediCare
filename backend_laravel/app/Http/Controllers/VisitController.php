<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use App\Models\Medication;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VisitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user('api');

        $visits = $user->visits()
            ->with('medications')
            ->orderBy('visited_on', 'desc')
            ->get();

        return response()->json([
            'visits' => $visits,
        ]);
    }

    public function storeApi(Request $request): JsonResponse
    {
        $user = $request->user('api');

        // Only employees may submit their own clinic visits.
        abort_unless($user->role === 'employee', 403);

        $data = $request->validate([
            'clinic' => ['required', 'string', 'max:255'],
            'visited_on' => ['required', 'date'],

            // Whole TWD amounts must fit the unsigned integer column after conversion.
            'covered_amount' => ['required', 'integer', 'min:0', 'max:42949672'],

            'diagnosis' => ['nullable', 'string', 'max:255'],
            'visited_time' => ['nullable', 'date_format:H:i'],
            'doctor_name' => ['nullable', 'string', 'max:255'],
            'doctor_title' => ['nullable', 'string', 'max:255'],

            // Sick leave requires both dates in chronological order.
            'sick_leave_started_on' => [
                'nullable',
                'required_with:sick_leave_ended_on',
                'date_format:Y-m-d',
            ],
            'sick_leave_ended_on' => [
                'nullable',
                'required_with:sick_leave_started_on',
                'date_format:Y-m-d',
                'after_or_equal:sick_leave_started_on',
            ],

            // Each medication is a breakdown already included in the visit amount.
            'medications' => ['sometimes', 'array', 'max:50'],
            'medications.*' => ['array:name,dosage,instructions,covered_amount'],
            'medications.*.name' => ['required', 'string', 'max:255'],
            'medications.*.dosage' => ['nullable', 'string', 'max:255'],
            'medications.*.instructions' => ['nullable', 'string', 'max:2000'],
            'medications.*.covered_amount' => [
                'required',
                'integer',
                'min:0',
                'max:42949672',
            ],
        ]);

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
            $visit = new Visit();
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
                $medication = new Medication();
                $medication->name = $item['name'];
                $medication->dosage = $item['dosage'] ?? null;
                $medication->instructions = $item['instructions'] ?? null;
                $medication->covered_amount_cents = (int) $item['covered_amount'] * 100;

                // Set visit_id through the relationship, never from client input.
                $visit->medications()->save($medication);
            }

            return $visit;
        });

        return response()->json([
            'message' => 'Visit recorded successfully.',
            'visit' => $visit->load('medications'),
        ], 201);
    }
}
