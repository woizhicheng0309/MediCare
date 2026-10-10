<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $medicare = Company::firstOrCreate(
                ['id' => 1],
                ['name' => 'MediCare'],
            );

            $microprogram = Company::firstOrCreate(
                ['id' => 2],
                ['name' => 'Microprogram'],
            );

            $horizon = Company::firstOrCreate(
                ['id' => 3],
                ['name' => 'Horizon'],
            );

            $accounts = [
                [
                    'name' => 'Alex Chen',
                    'email' => 'alex@microprogram.employee',
                    'legacy_email' => 'alex@example.test',
                    'role' => 'employee',
                    'company_id' => $microprogram->id,
                ],
                [
                    'name' => 'Jamie Lee',
                    'email' => 'jamie@horizon.employee',
                    'legacy_email' => 'jamie@example.test',
                    'role' => 'employee',
                    'company_id' => $horizon->id,
                ],
                [
                    'name' => 'Morgan Lin',
                    'email' => 'morgan@microprogram.hr',
                    'legacy_email' => 'morgan@example.test',
                    'role' => 'hr',
                    'company_id' => $microprogram->id,
                ],
                [
                    'name' => 'Taylor Wong',
                    'email' => 'taylor@medicare.helpdesk',
                    'legacy_email' => 'taylor@example.test',
                    'role' => 'helpdesk',
                    'company_id' => $medicare->id,
                ],
            ];

            foreach ($accounts as $account) {
                $user = User::where('email', $account['email'])->first()
                    ?? User::where('email', $account['legacy_email'])->first()
                    ?? new User();

                $user->name = $account['name'];
                $user->email = $account['email'];
                $user->role = $account['role'];
                $user->company_id = $account['company_id'];

                if (! $user->exists) {
                    $user->password = Hash::make('LearningDemo!2026');
                }

                $user->save();
            }
        });
    }
}