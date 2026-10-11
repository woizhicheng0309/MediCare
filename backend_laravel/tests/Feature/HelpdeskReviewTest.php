<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpdeskReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $helpdesk;
    private User $employee;

    // Prepare independent accounts before each test.
    protected function setUp(): void
    {
        parent::setUp(); // setUp will run before test everytime to get test accounts ready, initialize Laravel test env

        $medicare = new Company();
        $medicare->name = 'MediCare';
        $medicare->save();

        $microprogram = new Company();
        $microprogram->name = 'Microprogram';
        $microprogram->save();

        $this->helpdesk = User::factory()->create([
            'name' => 'Taylor Wong',
            'email' => 'taylor@medicare.helpdesk',
            'role' => 'helpdesk',
            'company_id' => $medicare->id,
        ]);

        $this->employee = User::factory()->create([
            'name' => 'Alex Chen',
            'email' => 'alex@microprogram.employee',
            'role' => 'employee',
            'company_id' => $microprogram->id,
        ]);
    }

    // Helpdesk can approve a pending visit belonging to an employee.
    public function test_helpdesk_can_approve_pending_visit(): void
    {
        $visit = $this->createVisit();

        $this->actingAs($this->helpdesk, 'api')
            ->patchJson("/api/helpdesk/visits/{$visit->id}/status", [
                'status' => 'approved',
            ])
            ->assertStatus(200)
            ->assertJsonPath('visit.status', 'approved');

        $this->assertDatabaseHas('visits', [
            'id' => $visit->id,
            'status' => 'approved',
        ]);
    }

    // Helpdesk can reject a pending visit belonging to an employee.
    public function test_helpdesk_can_reject_pending_visit(): void
    {
        $visit = $this->createVisit();

        $this->actingAs($this->helpdesk, 'api')
            ->patchJson("/api/helpdesk/visits/{$visit->id}/status", [
                'status' => 'rejected',
            ])
            ->assertStatus(200)
            ->assertJsonPath('visit.status', 'rejected');

        $this->assertDatabaseHas('visits', [
            'id' => $visit->id,
            'status' => 'rejected',
        ]);
    }

    // Employees cannot approve visits, including their own submissions.
    public function test_employee_cannot_review_visit(): void
    {
        $visit = $this->createVisit();

        $this->actingAs($this->employee, 'api')
            ->patchJson("/api/helpdesk/visits/{$visit->id}/status", [
                'status' => 'approved',
            ])
            ->assertStatus(403);

        // A denied request must leave the visit pending.
        $this->assertDatabaseHas('visits', [
            'id' => $visit->id,
            'status' => 'pending',
        ]);
    }

    // Review requests must only accept approved or rejected.
    public function test_invalid_review_status_is_rejected(): void
    {
        $visit = $this->createVisit();

        $this->actingAs($this->helpdesk, 'api')
            ->patchJson("/api/helpdesk/visits/{$visit->id}/status", [
                'status' => 'paid',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status'); // To confirm the errors source is from "status"

        $this->assertDatabaseHas('visits', [
            'id' => $visit->id,
            'status' => 'pending',
        ]);
    }

    // A second review must not overwrite the first review decision.
    public function test_processed_visit_cannot_be_reviewed_again(): void
    {
        $visit = $this->createVisit();

        $this->actingAs($this->helpdesk, 'api')
            ->patchJson("/api/helpdesk/visits/{$visit->id}/status", [
                'status' => 'approved',
            ])
            ->assertStatus(200);

        // Attempt to reverse the decision after the visit has been processed.
        $this->patchJson("/api/helpdesk/visits/{$visit->id}/status", [
            'status' => 'rejected',
        ])
            ->assertStatus(409);

        $this->assertDatabaseHas('visits', [
            'id' => $visit->id,
            'status' => 'approved',
        ]);
    }

    // Legacy visits without an owner must not be approved.
    public function test_ownerless_visit_cannot_be_reviewed(): void
    {
        $visit = $this->createVisit();

        // Simulate an existing record that has no employee association.
        $visit->user_id = null;
        $visit->save();

        $this->actingAs($this->helpdesk, 'api')
            ->patchJson("/api/helpdesk/visits/{$visit->id}/status", [
                'status' => 'approved',
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('visits', [
            'id' => $visit->id,
            'user_id' => null,
            'status' => 'pending',
        ]);
    }

    // Create a pending visit associated with the employee prepared in setUp.
    private function createVisit(): Visit
    {
        $visit = new Visit();
        $visit->clinic = 'Ann Family Clinic';
        $visit->visited_on = '2026-10-11';
        $visit->covered_amount_cents = 85000;
        $visit->status = 'pending';

        $this->employee->visits()->save($visit);

        return $visit;
    }
}