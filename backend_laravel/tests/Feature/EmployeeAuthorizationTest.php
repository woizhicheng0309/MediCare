<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    // Employees must only receive their own visit records.
    public function test_employee_only_lists_own_visits(): void
    {
        $alex = User::factory()->create([
            'name' => 'Alex Chen',
            'email' => 'alex@microprogram.employee',
            'role' => 'employee',
        ]);

        $jamie = User::factory()->create([
            'name' => 'Jamie Lee',
            'email' => 'jamie@horizon.employee',
            'role' => 'employee',
        ]);

        // Give each employee a visit so an unscoped query would return both.
        $alexVisit = $this->createVisit($alex, 'Ann Family Clinic');
        $this->createVisit($jamie, 'Horizon Clinic');

        $this->actingAs($alex, 'api')
            ->getJson('/api/visits')
            ->assertStatus(200)
            ->assertJsonCount(1, 'visits')
            ->assertJsonPath('visits.0.id', $alexVisit->id)
            ->assertJsonPath('visits.0.user_id', $alex->id);
    }

    // Visit ownership and initial status must be controlled by the server.
    public function test_employee_cannot_forge_visit_owner_or_status(): void
    {
        $alex = User::factory()->create([
            'name' => 'Alex Chen',
            'email' => 'alex@microprogram.employee',
            'role' => 'employee',
        ]);

        $jamie = User::factory()->create([
            'name' => 'Jamie Lee',
            'email' => 'jamie@horizon.employee',
            'role' => 'employee',
        ]);

        // Attempt to create an approved visit under another employee's account.
        $response = $this->actingAs($alex, 'api')
            ->postJson('/api/visits', [
                'clinic' => 'Ann Family Clinic',
                'visited_on' => '2026-10-11',
                'covered_amount' => 850,
                'user_id' => $jamie->id,
                'status' => 'approved',
            ]);

        // The API must use the authenticated employee and force pending status.
        $response
            ->assertStatus(201)
            ->assertJsonPath('visit.user_id', $alex->id)
            ->assertJsonPath('visit.status', 'pending')
            ->assertJsonPath('visit.covered_amount_cents', 85000);

        // Check persisted data as well as the response.
        $this->assertDatabaseHas('visits', [
            'id' => $response->json('visit.id'),
            'user_id' => $alex->id,
            'status' => 'pending',
            'covered_amount_cents' => 85000,
        ]);

        $this->assertDatabaseCount('visits', 1);
    }

    // Save through the relationship to associate the visit with its owner.
    private function createVisit(User $user, string $clinic): Visit
    {
        $visit = new Visit();
        $visit->clinic = $clinic;
        $visit->visited_on = '2026-10-11';
        $visit->covered_amount_cents = 85000;
        $visit->status = 'pending';

        $user->visits()->save($visit);

        return $visit;
    }
}