<?php

namespace Tests\Feature;

use App\Models\BenefitAssignment;
use App\Models\BenefitPlan;
use App\Models\Company;
use App\Models\Medication;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BenefitSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;
    private BenefitPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        // Fix the current year so these tests remain stable after a year change.
        $this->travelTo(Carbon::parse('2026-10-11 12:00:00'));

        $company = new Company();
        $company->name = 'Microprogram';
        $company->save();

        $this->employee = User::factory()->create([
            'name' => 'Alex Chen',
            'email' => 'alex@microprogram.employee',
            'role' => 'employee',
            'company_id' => $company->id,
        ]);

        // Store monetary values in cents; the API returns whole TWD amounts.
        $this->plan = new BenefitPlan();
        $this->plan->company_id = $company->id;
        $this->plan->name = 'Plus';
        $this->plan->annual_limit_cents = 18000 * 100;
        $this->plan->save();
    }

    // Only approved visits in the current calendar year count toward usage.
    public function test_summary_only_counts_current_year_approved_visits(): void
    {
        $this->assignPlan();

        $this->createVisit($this->employee, 850, 'approved', '2026-01-01');
        $this->createVisit($this->employee, 600, 'approved', '2026-10-10');

        // Pending, rejected, previous-year and next-year visits must be excluded.
        $this->createVisit($this->employee, 200, 'pending', '2026-10-10');
        $this->createVisit($this->employee, 100, 'rejected', '2026-10-10');
        $this->createVisit($this->employee, 900, 'approved', '2025-12-31');
        $this->createVisit($this->employee, 700, 'approved', '2027-01-01');

        $this->actingAs($this->employee, 'api')
            ->getJson('/api/benefits')
            ->assertStatus(200)
            ->assertJsonPath('year', 2026)
            ->assertJsonPath('currency', 'TWD')
            ->assertJsonPath('plan_name', 'Plus')
            ->assertJsonPath('annual_limit', 18000)
            ->assertJsonPath('used_amount', 1450)
            ->assertJsonPath('remaining_balance', 16550);
    }

    // Usage belongs to an individual employee, even within the same company.
    public function test_summary_excludes_other_employee_visits(): void
    {
        $this->assignPlan();

        $otherEmployee = User::factory()->create([
            'name' => 'Casey Lin',
            'email' => 'casey@microprogram.employee',
            'role' => 'employee',
            'company_id' => $this->employee->company_id,
        ]);

        $this->createVisit($this->employee, 850);
        $this->createVisit($otherEmployee, 600);

        $this->actingAs($this->employee, 'api')
            ->getJson('/api/benefits')
            ->assertStatus(200)
            ->assertJsonPath('used_amount', 850)
            ->assertJsonPath('remaining_balance', 17150);
    }

    // Medication amounts are already included in the visit's covered amount.
    public function test_summary_does_not_count_medication_amount_twice(): void
    {
        $this->assignPlan();

        $visit = $this->createVisit($this->employee, 850);

        $medication = new Medication();
        $medication->name = 'Paracetamol';
        $medication->covered_amount_cents = 300 * 100;

        $visit->medications()->save($medication);

        // Usage must remain 850, rather than adding the medication's 300 again.
        $this->actingAs($this->employee, 'api')
            ->getJson('/api/benefits')
            ->assertStatus(200)
            ->assertJsonPath('used_amount', 850)
            ->assertJsonPath('remaining_balance', 17150);
    }

    // A previous-year assignment does not provide coverage for the current year.
    public function test_summary_requires_current_year_assignment(): void
    {
        $this->assignPlan(2025);

        $this->actingAs($this->employee, 'api')
            ->getJson('/api/benefits')
            ->assertStatus(404)
            ->assertJsonPath(
                'message',
                'No benefit plan assigned for this year.',
            );
    }

    // Associate the employee with a benefit plan for a specific year.
    private function assignPlan(int $year = 2026): void
    {
        $assignment = new BenefitAssignment();
        $assignment->benefit_plan_id = $this->plan->id;
        $assignment->year = $year;

        $this->employee->benefitAssignments()->save($assignment);
    }

    // Accept whole TWD amounts and convert them to cents before saving.
    private function createVisit(
        User $user,
        int $coveredAmount,
        string $status = 'approved',
        string $visitedOn = '2026-10-11',
    ): Visit {
        $visit = new Visit();
        $visit->clinic = 'Ann Family Clinic';
        $visit->visited_on = $visitedOn;
        $visit->covered_amount_cents = $coveredAmount * 100;
        $visit->status = $status;

        $user->visits()->save($visit);

        return $visit;
    }
}