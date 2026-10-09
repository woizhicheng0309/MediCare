<?php

namespace App\Http\Controllers;

use App\Models\BenefitAssignment;
use App\Models\BenefitPlan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrBenefitAssignmentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $hr = $request->user('api');

        abort_unless($hr->role === 'hr', 403);
        abort_if($hr->company_id === null, 403);

        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'benefit_plan_id' => ['required', 'integer'],
        ]);

        $employee = User::where('company_id', $hr->company_id)
            ->where('role', 'employee')
            ->findOrFail($data['user_id']);

        $plan = BenefitPlan::where('company_id', $hr->company_id)
            ->findOrFail($data['benefit_plan_id']);

        $year = now()->year;

        $assignment = $employee->benefitAssignments()
            ->where('year', $year)
            ->first();

        if ($assignment === null) {
            $assignment = new BenefitAssignment();
        }

        $assignment->year = $year;
        $assignment->benefit_plan_id = $plan->id;

        $employee->benefitAssignments()->save($assignment);

        return response()->json([
            'message' => 'Benefit plan assigned successfully.',
            'assignment' => $assignment,
        ]);
    }
}