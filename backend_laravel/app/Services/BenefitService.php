<?php

namespace App\Services;

use App\Models\User;

// Keep business operations and data access separate from HTTP responses.
class BenefitService
{
    public function summary(User $user): ?array
    {
        $year = now()->year;

        $assignment = $user->benefitAssignments()
            ->where('year', $year)
            ->first();

        if ($assignment === null) {
            return null;
        }

        $plan = $assignment->benefitPlan;

        abort_unless($plan->company_id === $user->company_id, 403);

        $annualLimit = (int) ($plan->annual_limit_cents / 100);

        $usedAmountCents = $user->visits()
            ->where('status', 'approved')
            ->whereYear('visited_on', $year)
            ->sum('covered_amount_cents');

        $usedAmount = (int) ($usedAmountCents / 100);

        return [
            'year' => $year,
            'currency' => 'TWD',
            'plan_name' => $plan->name,
            'annual_limit' => $annualLimit,
            'used_amount' => $usedAmount,
            'remaining_balance' => $annualLimit - $usedAmount,
        ];

    }
}
