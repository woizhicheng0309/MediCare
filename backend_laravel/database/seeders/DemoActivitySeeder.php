<?php

namespace Database\Seeders;

use App\Models\BenefitAssignment;
use App\Models\BenefitPlan;
use App\Models\Company;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DemoActivitySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $companies = [
                'Microprogram' => [
                    'domain' => 'microprogram',
                    'employees' => [
                        ['Alex Chen', 'alex'],
                        ['Emma Lin', 'emma'],
                        ['Noah Wang', 'noah'],
                        ['Olivia Huang', 'olivia'],
                        ['Liam Wu', 'liam'],
                        ['Ava Liu', 'ava'],
                        ['Ethan Tsai', 'ethan'],
                        ['Mia Chang', 'mia'],
                    ],
                ],
                'Horizon' => [
                    'domain' => 'horizon',
                    'employees' => [
                        ['Jamie Lee', 'jamie'],
                        ['Lucas Chen', 'lucas'],
                        ['Sophia Lin', 'sophia'],
                        ['Henry Wang', 'henry'],
                        ['Isabella Wu', 'isabella'],
                        ['Oliver Liu', 'oliver'],
                        ['Amelia Tsai', 'amelia'],
                        ['Leo Huang', 'leo'],
                    ],
                ],
            ];

            $password = Hash::make('LearningDemo!2026');
            $year = now()->year;

            foreach ($companies as $companyName => $data) {
                $company = Company::where('name', $companyName)
                    ->sole();

                $standard = BenefitPlan::where('company_id', $company->id)
                    ->where('name', 'Standard')
                    ->sole();

                $plus = BenefitPlan::where('company_id', $company->id)
                    ->where('name', 'Plus')
                    ->first();

                foreach ($data['employees'] as $index => [$name, $username]) {
                    $email = "{$username}@{$data['domain']}.employee";

                    $employee = User::firstOrCreate(
                        ['email' => $email],
                        [
                            'name' => $name,
                            'password' => $password,
                            'role' => 'employee',
                            'company_id' => $company->id,
                        ],
                    );

                    if (
                        $employee->role !== 'employee'
                        || (int) $employee->company_id !== (int) $company->id
                    ) {
                        throw new RuntimeException(
                            "Demo account role or company mismatch: {$email}"
                        );
                    }

                    $plan = $index % 2 === 0
                        ? ($plus ?? $standard)
                        : $standard;

                    BenefitAssignment::firstOrCreate(
                        [
                            'user_id' => $employee->id,
                            'year' => $year,
                        ],
                        [
                            'benefit_plan_id' => $plan->id,
                        ],
                    );
                }
            }
            $alex = User::where('email', 'alex@microprogram.employee')
                ->where('role', 'employee')
                ->sole();

            $reviewer = User::where('email', 'taylor@medicare.helpdesk')
                ->where('role', 'helpdesk')
                ->sole();

            $this->seedAlexVisits($alex, $reviewer);
        });
    }

    private function seedAlexVisits(User $employee, User $reviewer): void
    {
        $samples = [
            [
                'clinic' => 'Central Clinic',
                'date' => '2026-05-12',
                'time' => '09:30:00',
                'amount' => 600,
                'status' => 'approved',
                'diagnosis' => 'Migraine',
                'doctor' => 'Dr Lim',
                'leave_days' => 0,
                'medications' => [['Paracetamol', 150]],
            ],
            [
                'clinic' => 'Park Clinic',
                'date' => '2026-06-18',
                'time' => '14:15:00',
                'amount' => 950,
                'status' => 'approved',
                'diagnosis' => 'Upper respiratory tract infection',
                'doctor' => 'Dr Wong',
                'leave_days' => 2,
                'medications' => [
                    ['Paracetamol', 150],
                    ['Cough syrup', 200],
                ],
            ],
            [
                'clinic' => 'Ann Family Clinic',
                'date' => '2026-09-03',
                'time' => '11:00:00',
                'amount' => 800,
                'status' => 'pending',
                'diagnosis' => 'Allergic rhinitis',
                'doctor' => 'Dr Tan',
                'leave_days' => 0,
                'medications' => [['Antihistamine', 250]],
            ],
            [
                'clinic' => 'Riverside Clinic',
                'date' => '2026-09-15',
                'time' => '16:45:00',
                'amount' => 500,
                'status' => 'rejected',
                'diagnosis' => 'Routine health check',
                'doctor' => 'Dr Lee',
                'leave_days' => 0,
                'medications' => [],
            ],
        ];

        foreach ($samples as $sample) {
            $visitedOn = CarbonImmutable::parse($sample['date']);
            $reviewed = $sample['status'] !== 'pending';

            $visit = Visit::firstOrCreate(
                [
                    'user_id' => $employee->id,
                    'clinic' => $sample['clinic'],
                    'visited_on' => $sample['date'],
                    'visited_time' => $sample['time'],
                ],
                [
                    'covered_amount_cents' => $sample['amount'] * 100,
                    'status' => $sample['status'],
                    'diagnosis' => $sample['diagnosis'],
                    'doctor_name' => $sample['doctor'],
                    'doctor_title' => 'General Practitioner',
                    'sick_leave_started_on' => $sample['leave_days'] > 0
                        ? $sample['date']
                        : null,
                    'sick_leave_ended_on' => $sample['leave_days'] > 0
                        ? $visitedOn->addDays($sample['leave_days'] - 1)->toDateString()
                        : null,
                    'reviewed_by' => $reviewed ? $reviewer->id : null,
                    'reviewed_at' => $reviewed
                        ? $visitedOn->addDay()->setTime(10, 0)
                        : null,
                ],
            );

            // Preserve existing visits, including any subsequent review decisions.
            if (! $visit->wasRecentlyCreated) {
                continue;
            }

            foreach ($sample['medications'] as [$name, $amount]) {
                $visit->medications()->create([
                    'name' => $name,
                    'covered_amount_cents' => $amount * 100,
                ]);
            }
        }
    }
}
