<?php

namespace App\Services;

use App\Models\BenefitPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

// Keep business operations and data access separate from HTTP responses.
class HrBenefitPlanService
{
    public function plans(User $user): Collection
    {

        abort_unless($user->role === 'hr', 403);
        abort_if($user->company_id === null, 403);

        $plans = BenefitPlan::where('company_id', $user->company_id)
            ->get(['id', 'name', 'company_id', 'annual_limit_cents']);

        return $plans;
    }
}
