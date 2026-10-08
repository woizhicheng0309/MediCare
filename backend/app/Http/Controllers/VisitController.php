<?php

namespace App\Http\Controllers;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use App\Models\Visit;
use Illuminate\Http\RedirectResponse;

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
}
