<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;

class BenefitController extends Controller
{
    public function index()
    {
        return view('benefits');
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user('api');
        $year = now()->year;

        $assignment = $user->benefitAssignments()
            ->where('year', $year)
            ->first();

        if ($assignment === null) {
            return response()->json([
                'message' => 'No benefit plan assigned for this year.',
            ], 404);
        }

        $plan = $assignment->benefitPlan;

        abort_unless($plan->company_id === $user->company_id, 403);

        $annualLimit = (int) ($plan->annual_limit_cents / 100);

        $usedAmountCents = $user->visits()
            ->where('status', 'approved')
            ->whereYear('visited_on', $year)
            ->sum('covered_amount_cents');

        $usedAmount = (int) ($usedAmountCents / 100);

        return response()->json([
            'year' => $year,
            'currency' => 'TWD',
            'plan_name' => $plan->name,
            'annual_limit' => $annualLimit,
            'used_amount' => $usedAmount,
            'remaining_balance' => $annualLimit - $usedAmount,
        ]);
    }
}
