<?php

namespace Database\Seeders;

use App\Models\BenefitAssignment;
use App\Models\BenefitPlan;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BenefitSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $microprogram = Company::where('name', 'Microprogram')
                ->firstOrFail();

            $horizon = Company::where('name', 'Horizon')
                ->firstOrFail();

            BenefitPlan::firstOrCreate(
                [
                    'company_id' => $microprogram->id,
                    'name' => 'Standard',
                ],
                [
                    'annual_limit_cents' => 12000 * 100,
                ],
            );

            $microprogramPlus = BenefitPlan::firstOrCreate(
                [
                    'company_id' => $microprogram->id,
                    'name' => 'Plus',
                ],
                [
                    'annual_limit_cents' => 18000 * 100,
                ],
            );

            $horizonStandard = BenefitPlan::firstOrCreate(
                [
                    'company_id' => $horizon->id,
                    'name' => 'Standard',
                ],
                [
                    'annual_limit_cents' => 15000 * 100,
                ],
            );

            $alex = User::where('email', 'alex@microprogram.employee')
                ->where('company_id', $microprogram->id)
                ->where('role', 'employee')
                ->firstOrFail();

            $jamie = User::where('email', 'jamie@horizon.employee')
                ->where('company_id', $horizon->id)
                ->where('role', 'employee')
                ->firstOrFail();

            $year = now()->year;

            BenefitAssignment::firstOrCreate(
                [
                    'user_id' => $alex->id,
                    'year' => $year,
                ],
                [
                    'benefit_plan_id' => $microprogramPlus->id,
                ],
            );

            BenefitAssignment::firstOrCreate(
                [
                    'user_id' => $jamie->id,
                    'year' => $year,
                ],
                [
                    'benefit_plan_id' => $horizonStandard->id,
                ],
            );
        });
    }
}