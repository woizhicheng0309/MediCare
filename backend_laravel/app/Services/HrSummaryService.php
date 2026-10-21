<?php

namespace App\Services;

use App\Models\User;
use App\Models\Visit;

class HrSummaryService
{
    public function summary(User $hr, int $year): array
    {
        abort_unless($hr->role === 'hr', 403);
        abort_if($hr->company_id === null, 403);

        $employeeCount = User::where('company_id', $hr->company_id)
            ->where('role', 'employee')
            ->count();

        $totals = Visit::whereHas('user', function ($query) use ($hr) {
            $query->where('company_id', $hr->company_id)
                ->where('role', 'employee');
        })
            ->where('status', 'approved')
            ->whereYear('visited_on', $year)
            ->selectRaw(
                'COUNT(*) AS visit_count,
                 COALESCE(SUM(covered_amount_cents), 0) AS used_amount_cents'
            )
            ->first();

        return [
            'year' => $year,
            'currency' => 'TWD',
            'employee_count' => $employeeCount,
            'approved_visit_count' => (int) $totals->visit_count,
            'used_amount' => (int) ($totals->used_amount_cents / 100),
        ];
    }
    public function clinicStatistics(User $hr, int $year): array
    {
        abort_unless($hr->role === 'hr', 403);
        abort_if($hr->company_id === null, 403);

        $clinics = Visit::whereHas('user', function ($query) use ($hr) {
            $query->where('company_id', $hr->company_id)
                ->where('role', 'employee');
        })
            ->where('status', 'approved')
            ->whereYear('visited_on', $year)
            ->select('clinic')
            ->selectRaw(
                'COUNT(*) AS approved_visit_count,
                SUM(covered_amount_cents) AS used_amount_cents'
            )
            ->groupBy('clinic')
            ->orderByDesc('approved_visit_count')
            ->orderBy('clinic')
            ->limit(5)
            ->get();

        return [
            'year' => $year,
            'currency' => 'TWD',
            'clinics' => $clinics->map(fn (Visit $row): array => [
                'clinic' => $row->clinic,
                'approved_visit_count' => (int) $row->approved_visit_count,
                'used_amount' => (int) ($row->used_amount_cents / 100),
            ])->all(),
        ];
    }
}