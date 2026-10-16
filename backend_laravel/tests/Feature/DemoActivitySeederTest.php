<?php

namespace Tests\Feature;

use App\Models\BenefitAssignment;
use App\Models\BenefitPlan;
use App\Models\Medication;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoActivitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoActivitySeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-16 12:00:00'));
        $this->seed(DatabaseSeeder::class);
    }

    public function test_demo_data_has_valid_company_scopes_amounts_and_review_details(): void
    {
        $this->seed(DemoActivitySeeder::class);

        $this->assertSame(16, User::where('role', 'employee')->count());
        $this->assertSame(2, User::where('role', 'hr')->count());
        $this->assertDatabaseCount('benefit_assignments', 16);
        $this->assertDatabaseCount('visits', 64);
        $this->assertDatabaseCount('medications', 64);
        $this->assertSame(32, Visit::where('status', 'approved')->count());
        $this->assertSame(16, Visit::where('status', 'pending')->count());
        $this->assertSame(16, Visit::where('status', 'rejected')->count());

        foreach (User::where('role', 'employee')->get() as $employee) {
            $assignment = BenefitAssignment::where('user_id', $employee->id)->sole();
            $plan = BenefitPlan::findOrFail($assignment->benefit_plan_id);
            $this->assertSame((int) $employee->company_id, (int) $plan->company_id);
            $this->assertSame(4, $employee->visits()->count());
            $this->assertLessThanOrEqual(
                $plan->annual_limit_cents,
                $employee->visits()->where('status', 'approved')->sum('covered_amount_cents'),
            );
        }

        foreach (Visit::with(['medications', 'reviewer'])->get() as $visit) {
            $this->assertLessThanOrEqual(
                $visit->covered_amount_cents,
                $visit->medications->sum('covered_amount_cents'),
            );
            $this->assertLessThanOrEqual(now()->toDateString(), $visit->visited_on);

            if ($visit->status === 'pending') {
                $this->assertNull($visit->reviewed_by);
                $this->assertNull($visit->reviewed_at);
            } else {
                $this->assertSame('helpdesk', $visit->reviewer->role);
                $this->assertNotNull($visit->reviewed_at);
            }
        }
    }

    public function test_rerun_preserves_reviews_plan_changes_and_unrelated_visits(): void
    {
        $this->seed(DemoActivitySeeder::class);
        $alex = User::where('email', 'alex@microprogram.employee')->sole();
        $reviewer = User::where('role', 'helpdesk')->sole();
        $pending = $alex->visits()->where('status', 'pending')->sole();
        $pending->status = 'approved';
        $pending->reviewed_by = $reviewer->id;
        $pending->reviewed_at = now();
        $pending->save();

        $assignment = BenefitAssignment::where('user_id', $alex->id)->sole();
        $assignment->benefit_plan_id = BenefitPlan::where('company_id', $alex->company_id)
            ->where('name', 'Standard')->sole()->id;
        $assignment->save();

        $manual = new Visit();
        $manual->user_id = $alex->id;
        $manual->clinic = 'West Clinic';
        $manual->visited_on = '2026-10-01';
        $manual->covered_amount_cents = 10000;
        $manual->status = 'pending';
        $manual->save();

        $before = [
            User::orderBy('id')->get()->toArray(),
            BenefitAssignment::orderBy('id')->get()->toArray(),
            Visit::orderBy('id')->get()->toArray(),
            Medication::orderBy('id')->get()->toArray(),
        ];

        $this->seed(DemoActivitySeeder::class);

        $this->assertSame($before, [
            User::orderBy('id')->get()->toArray(),
            BenefitAssignment::orderBy('id')->get()->toArray(),
            Visit::orderBy('id')->get()->toArray(),
            Medication::orderBy('id')->get()->toArray(),
        ]);
    }
}
