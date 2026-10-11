<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitMedicationSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;
    private User $hr;
    private User $helpdesk;

    protected function setUp(): void
    {
        parent::setUp();

        // Employee and HR belong to a client company.
        $company = new Company();
        $company->name = 'Microprogram';
        $company->save();

        $this->employee = User::factory()->create([
            'role' => 'employee',
            'company_id' => $company->id,
        ]);

        $this->hr = User::factory()->create([
            'role' => 'hr',
            'company_id' => $company->id,
        ]);

        // Helpdesk belongs to the TPA and serves client companies.
        $medicare = new Company();
        $medicare->name = 'MediCare';
        $medicare->save();

        $this->helpdesk = User::factory()->create([
            'role' => 'helpdesk',
            'company_id' => $medicare->id,
        ]);
    }

    // Save multiple medications and expose them to authorized readers.
    public function test_medications_are_saved_and_visible_to_authorized_readers(): void
    {
        $response = $this->actingAs($this->employee, 'api')
            ->postJson('/api/visits', array_merge($this->visitPayload(), [
                'medications' => [
                    [
                        'name' => 'Paracetamol',
                        'dosage' => '500 mg',
                        'instructions' => 'Take after meals.',
                        'covered_amount' => 300,
                    ],
                    [
                        'name' => 'Cetirizine',
                        'covered_amount' => 200,
                    ],
                ],
            ]))
            ->assertStatus(201)
            ->assertJsonPath('visit.covered_amount_cents', 85000)
            ->assertJsonPath('visit.status', 'pending')
            ->assertJsonCount(2, 'visit.medications')
            ->assertJsonPath('visit.medications.0.covered_amount_cents', 30000)
            ->assertJsonPath('visit.medications.1.dosage', null)
            ->assertJsonPath('visit.medications.1.instructions', null);

        $visitId = $response->json('visit.id');

        $this->assertDatabaseHas('medications', [
            'visit_id' => $visitId,
            'name' => 'Paracetamol',
            'dosage' => '500 mg',
            'instructions' => 'Take after meals.',
            'covered_amount_cents' => 30000,
        ]);

        $this->assertDatabaseHas('medications', [
            'visit_id' => $visitId,
            'name' => 'Cetirizine',
            'covered_amount_cents' => 20000,
        ]);

        $this->assertDatabaseCount('visits', 1);
        $this->assertDatabaseCount('medications', 2);

        // Medication details must appear in all three authorized workflows.
        $this->getJson('/api/visits')
            ->assertStatus(200)
            ->assertJsonCount(2, 'visits.0.medications');

        $this->actingAs($this->hr, 'api')
            ->getJson("/api/hr/employees/{$this->employee->id}/visits")
            ->assertStatus(200)
            ->assertJsonCount(2, 'visits.0.medications');

        $this->actingAs($this->helpdesk, 'api')
            ->getJson('/api/helpdesk/visits')
            ->assertStatus(200)
            ->assertJsonCount(2, 'visits.0.medications');
    }

    // Visits remain valid when medications are omitted or explicitly empty.
    public function test_medications_can_be_omitted_or_empty(): void
    {
        foreach ([
            $this->visitPayload(),
            array_merge($this->visitPayload(), ['medications' => []]),
        ] as $payload) {
            $this->actingAs($this->employee, 'api')
                ->postJson('/api/visits', $payload)
                ->assertStatus(201)
                ->assertJsonCount(0, 'visit.medications');
        }

        $this->assertDatabaseCount('visits', 2);
        $this->assertDatabaseCount('medications', 0);
    }

    // Invalid medication fields must not leave a visit or any medications.
    public function test_invalid_medications_do_not_create_partial_records(): void
    {
        $cases = [
            [['name' => '', 'covered_amount' => 100], 'medications.1.name'],
            [['name' => 'Paracetamol'], 'medications.1.covered_amount'],
            [['name' => 'Paracetamol', 'covered_amount' => -1], 'medications.1.covered_amount'],
            [['name' => 'Paracetamol', 'covered_amount' => 1.5], 'medications.1.covered_amount'],
            [['name' => str_repeat('A', 256), 'covered_amount' => 100], 'medications.1.name'],
            [['name' => 'Paracetamol', 'dosage' => str_repeat('A', 256), 'covered_amount' => 100], 'medications.1.dosage'],
            [['name' => 'Paracetamol', 'instructions' => str_repeat('A', 2001), 'covered_amount' => 100], 'medications.1.instructions'],
        ];

        foreach ($cases as [$invalidMedication, $errorField]) {
            $this->actingAs($this->employee, 'api')
                ->postJson('/api/visits', array_merge($this->visitPayload(), [
                    'medications' => [
                        ['name' => 'Cetirizine', 'covered_amount' => 100],
                        $invalidMedication,
                    ],
                ]))
                ->assertStatus(422)
                ->assertJsonValidationErrors($errorField);

            $this->assertDatabaseCount('visits', 0);
            $this->assertDatabaseCount('medications', 0);
        }
    }

    // Medication totals may equal visit coverage, but cannot exceed it.
    public function test_medication_total_cannot_exceed_visit_coverage(): void
    {
        $payload = array_merge($this->visitPayload(), [
            'medications' => [
                ['name' => 'Paracetamol', 'covered_amount' => 500],
                ['name' => 'Cetirizine', 'covered_amount' => 351],
            ],
        ]);

        $this->actingAs($this->employee, 'api')
            ->postJson('/api/visits', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('medications');

        $this->assertDatabaseCount('visits', 0);
        $this->assertDatabaseCount('medications', 0);

        // The exact boundary is valid: 500 + 350 = 850.
        $payload['medications'][1]['covered_amount'] = 350;

        $this->postJson('/api/visits', $payload)
            ->assertStatus(201)
            ->assertJsonPath('visit.covered_amount_cents', 85000)
            ->assertJsonCount(2, 'visit.medications');
    }

    // HR and Helpdesk must not submit employee visits.
    public function test_non_employee_roles_cannot_submit_visits(): void
    {
        foreach ([$this->hr, $this->helpdesk] as $user) {
            $this->actingAs($user, 'api')
                ->postJson('/api/visits', $this->visitPayload())
                ->assertStatus(403);
        }

        $this->assertDatabaseCount('visits', 0);
        $this->assertDatabaseCount('medications', 0);
    }

    // Medication ownership must come from the saved visit relationship.
    public function test_medication_rejects_client_supplied_visit_id(): void
    {
        $this->actingAs($this->employee, 'api')
            ->postJson('/api/visits', array_merge($this->visitPayload(), [
                'medications' => [
                    [
                        'name' => 'Paracetamol',
                        'covered_amount' => 100,
                        'visit_id' => 999,
                    ],
                ],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('medications.0');

        $this->assertDatabaseCount('visits', 0);
        $this->assertDatabaseCount('medications', 0);
    }

    // Reuse a valid visit payload to isolate medication behavior.
    private function visitPayload(): array
    {
        return [
            'clinic' => 'Ann Family Clinic',
            'visited_on' => '2026-10-11',
            'covered_amount' => 850,
        ];
    }
}