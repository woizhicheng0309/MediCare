<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrAuthorizationTest extends TestCase
{
    use RefreshDatabase;
    // HR employee lists must include only employees from the HR user's company.
    public function test_hr_only_lists_employees_from_own_company(): void
    {
        $microprogram = $this->createCompany('Microprogram');
        $horizon = $this->createCompany('Horizon');

        $hr = $this->createUser(
            'Morgan Lin',
            'morgan@microprogram.hr',
            'hr',
            $microprogram,
        );

        $alex = $this->createUser(
            'Alex Chen',
            'alex@microprogram.employee',
            'employee',
            $microprogram,
        );

        $this->createUser(
            'Jamie Lee',
            'jamie@horizon.employee',
            'employee',
            $horizon,
        );
        // The list must contain Alex only, excluding Jamie and HR user
        $this->actingAs($hr, 'api')
            ->getJson('/api/hr/employees')
            ->assertStatus(200)
            ->assertJsonCount(1, 'employees')
            ->assertJsonPath('employees.0.id', $alex->id);
    }
    // Verify own-company access before checking that cross-company access returns 404.
    public function test_hr_cannot_view_another_company_employee_visits(): void
    {
        $microprogram = $this->createCompany('Microprogram');
        $horizon = $this->createCompany('Horizon');

        $hr = $this->createUser(
            'Morgan Lin',
            'morgan@microprogram.hr',
            'hr',
            $microprogram,
        );

        $alex = $this->createUser(
            'Alex Chen',
            'alex@microprogram.employee',
            'employee',
            $microprogram,
        );

        $jamie = $this->createUser(
            'Jamie Lee',
            'jamie@horizon.employee',
            'employee',
            $horizon,
        );
        // Confirm that the endpoint allows access to an employee from the same company.
        $this->actingAs($hr, 'api')
            ->getJson("/api/hr/employees/{$alex->id}/visits")
            ->assertStatus(200)
            ->assertJsonPath('employee.id', $alex->id);
        // Return 404 for employees outside the HR user's company scope.
        $this->getJson("/api/hr/employees/{$jamie->id}/visits")
            ->assertStatus(404);
    }
    // Authentication alone does not grant access to endpoints restricted to HR.
    public function test_employee_cannot_access_hr_employee_list(): void
    {
        $microprogram = $this->createCompany('Microprogram');

        $alex = $this->createUser(
            'Alex Chen',
            'alex@microprogram.employee',
            'employee',
            $microprogram,
        );

        $this->actingAs($alex, 'api')
            ->getJson('/api/hr/employees')
            ->assertStatus(403);
    }
    // Create an independent company record for each test.
    private function createCompany(string $name): Company
    {
        $company = new Company();
        $company->name = $name;
        $company->save();

        return $company;
    }
    // Use factory defaults for other fields and explicitly set the user's identity and scope.
    private function createUser(
        string $name,
        string $email,
        string $role,
        Company $company,
    ): User {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'company_id' => $company->id,
        ]);
    }
}