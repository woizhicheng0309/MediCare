<?php

namespace App\Http\Controllers;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use App\Models\Visit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

class VisitController extends Controller
{
    public function create(): View
    {
        return view('visits.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'clinic' => ['required', 'string', 'max:255'],
            'visited_on' => ['required', 'date'],
            'covered_amount' => ['required', 'integer', 'min:0'],
        ]);

        $visit = new Visit();

        $visit->clinic = $data['clinic'];
        $visit->visited_on = $data['visited_on'];
        $visit->covered_amount_cents = (int) $data['covered_amount'] * 100;
        $visit->status = 'pending';

        $visit->save();

        return redirect('benefits.index')
        ->with('success', 'Visit recorded successfully.');
    }

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

        $data = $request->validate([
        'clinic' => ['required', 'string', 'max:255'],
        'visited_on' => ['required', 'date'],
        'covered_amount' => ['required', 'integer', 'min:0'],

        // Diagnosis is optional, with a maximum length matching the database column.
        'diagnosis' => ['nullable', 'string', 'max:255'],

        // Leave dates must either both be absent or both be provided.
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
    ]);

        $visit = new Visit();
        $visit->clinic = $data['clinic'];
        $visit->visited_on = $data['visited_on'];
        $visit->covered_amount_cents = (int) $data['covered_amount'] * 100;
        $visit->status = 'pending';
        // Persist validated medical fields; omitted optional fields remain null.
        $visit->diagnosis = $data['diagnosis'] ?? null;
        $visit->sick_leave_started_on = $data['sick_leave_started_on'] ?? null;
        $visit->sick_leave_ended_on = $data['sick_leave_ended_on'] ?? null;

        $user->visits()->save($visit);

        return response()->json([
            'message' => 'Visit recorded successfully.',
            'visit' => $visit,
        ], 201);
    }
}
