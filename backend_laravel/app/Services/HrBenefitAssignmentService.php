<?php

namespace App\Services;

use App\Models\BenefitAssignment;
use App\Models\BenefitPlan;
use App\Models\User;

// Keep business operations and data access separate from HTTP responses.
class HrBenefitAssignmentService
{
    public function assign(User $hr, array $data): BenefitAssignment
    {

        abort_unless($hr->role === 'hr', 403);
        abort_if($hr->company_id === null, 403);

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
            $assignment = new BenefitAssignment;
        }

        $assignment->year = $year;
        $assignment->benefit_plan_id = $plan->id;

        $employee->benefitAssignments()->save($assignment);

        return $assignment;
    }
}
