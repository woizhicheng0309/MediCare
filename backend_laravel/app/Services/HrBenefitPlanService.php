<?php

namespace App\Services;

use App\Models\BenefitPlan;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Predis\Connection\ConnectionException;
use Predis\Connection\Resource\Exception\StreamInitException;

class HrBenefitPlanService
{
    public function plans(User $user): array
    {
        abort_unless($user->role === 'hr', 403);
        abort_if($user->company_id === null, 403);

        $fetchPlans = fn (): array => BenefitPlan::where(
            'company_id',
            $user->company_id
        )->get([
            'id',
            'name',
            'company_id',
            'annual_limit_cents',
        ])->toArray();

        $store = config('cache.benefit_plans_store');

        if ($store === null) {
            return $fetchPlans();
        }

        $key = sprintf(
            '%s:company:%d:benefit_plans:v1',
            app()->environment(),
            $user->company_id
        );

        try {
            return Cache::store($store)->remember($key, 300, $fetchPlans);
        } catch (ConnectionException | StreamInitException $exception) {
            report($exception);

            return $fetchPlans();
        }
    }
}