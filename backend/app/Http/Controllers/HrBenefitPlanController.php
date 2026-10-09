<?php

namespace App\Http\Controllers;

use App\Models\BenefitPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrBenefitPlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user('api');

        abort_unless($user->role === 'hr', 403);
        abort_if($user->company_id === null, 403);

        $plans = BenefitPlan::where('company_id', $user->company_id)
            ->get(['id', 'name', 'company_id', 'annual_limit_cents']);

        return response()->json([
            'benefit_plans' => $plans,
        ]);
    }
}