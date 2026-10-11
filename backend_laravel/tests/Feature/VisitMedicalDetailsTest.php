<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitMedicalDetailsTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;
    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        // Prepare an employee and HR user belonging to the same company.
        $company = new Company();
        $company->name = 'Microprogram';
        $company->save();

        $this->employee = User::factory()->create([
            'name' => 'Alex Chen',
            'email' => 'alex@microprogram.employee',
            'role' => 'employee',
            'company_id' => $company->id,
        ]);

        $this->hr = User::factory()->create([
            'name' => 'Morgan Lin',
            'email' => 'morgan@microprogram.hr',
            'role' => 'hr',
            'company_id' => $company->id,
        ]);
    }

    // Save medical details and verify that authorized readers receive them.
    public function test_medical_details_are_saved_and_visible_to_own_company_hr(): void
    {
        $payload = array_merge($this->visitPayload(), [
            'diagnosis' => 'Upper respiratory tract infection',
            'sick_leave_started_on' => '2026-10-11',
            'sick_leave_ended_on' => '2026-10-12',
        ]);

        $response = $this->actingAs($this->employee, 'api')
            ->postJson('/api/visits', $payload)
            ->assertStatus(201)
            ->assertJsonPath('visit.diagnosis', $payload['diagnosis'])
            ->assertJsonPath('visit.sick_leave_started_on', '2026-10-11')
            ->assertJsonPath('visit.sick_leave_ended_on', '2026-10-12');

        $this->assertDatabaseHas('visits', [
            'id' => $response->json('visit.id'),
            'user_id' => $this->employee->id,
            'diagnosis' => $payload['diagnosis'],
            'sick_leave_started_on' => '2026-10-11',
            'sick_leave_ended_on' => '2026-10-12',
        ]);

        // The employee's own visit list must include the saved medical details.
        $this->getJson('/api/visits')
            ->assertStatus(200)
            ->assertJsonPath('visits.0.diagnosis', $payload['diagnosis'])
            ->assertJsonPath('visits.0.sick_leave_started_on', '2026-10-11')
            ->assertJsonPath('visits.0.sick_leave_ended_on', '2026-10-12');

        // Switch identity to confirm that same-company HR can read the details.
        $this->actingAs($this->hr, 'api')
            ->getJson("/api/hr/employees/{$this->employee->id}/visits")
            ->assertStatus(200)
            ->assertJsonPath('visits.0.diagnosis', $payload['diagnosis'])
            ->assertJsonPath('visits.0.sick_leave_started_on', '2026-10-11')
            ->assertJsonPath('visits.0.sick_leave_ended_on', '2026-10-12');
    }

    // Visits without diagnosis or sick leave must remain valid.
    public function test_medical_details_can_be_omitted(): void
    {
        $response = $this->actingAs($this->employee, 'api')
            ->postJson('/api/visits', $this->visitPayload())
            ->assertStatus(201)
            ->assertJsonPath('visit.diagnosis', null)
            ->assertJsonPath('visit.sick_leave_started_on', null)
            ->assertJsonPath('visit.sick_leave_ended_on', null);

        $this->assertDatabaseHas('visits', [
            'id' => $response->json('visit.id'),
            'diagnosis' => null,
            'sick_leave_started_on' => null,
            'sick_leave_ended_on' => null,
        ]);
    }

    // Providing a start date requires an end date.
    public function test_sick_leave_requires_end_date_when_start_is_provided(): void
    {
        $payload = array_merge($this->visitPayload(), [
            'sick_leave_started_on' => '2026-10-11',
        ]);

        $this->actingAs($this->employee, 'api')
            ->postJson('/api/visits', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('sick_leave_ended_on');

        // Invalid submissions must not create partial visit records.
        $this->assertDatabaseCount('visits', 0);
    }

    // Providing an end date requires a start date.
    public function test_sick_leave_requires_start_date_when_end_is_provided(): void
    {
        $payload = array_merge($this->visitPayload(), [
            'sick_leave_ended_on' => '2026-10-12',
        ]);

        $this->actingAs($this->employee, 'api')
            ->postJson('/api/visits', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('sick_leave_started_on');

        $this->assertDatabaseCount('visits', 0);
    }

    // Sick leave cannot end before it starts.
    public function test_sick_leave_end_cannot_precede_start(): void
    {
        $payload = array_merge($this->visitPayload(), [
            'sick_leave_started_on' => '2026-10-12',
            'sick_leave_ended_on' => '2026-10-11',
        ]);

        $this->actingAs($this->employee, 'api')
            ->postJson('/api/visits', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('sick_leave_ended_on');

        $this->assertDatabaseCount('visits', 0);
    }

    // Leave dates must be real calendar dates in YYYY-MM-DD format.
    public function test_sick_leave_rejects_invalid_calendar_date(): void
    {
        $payload = array_merge($this->visitPayload(), [
            'sick_leave_started_on' => '2026-02-30',
            'sick_leave_ended_on' => '2026-03-01',
        ]);

        $this->actingAs($this->employee, 'api')
            ->postJson('/api/visits', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('sick_leave_started_on');

        $this->assertDatabaseCount('visits', 0);
    }

    // Supply the required visit fields shared by these submission tests.
    private function visitPayload(): array
    {
        return [
            'clinic' => 'Ann Family Clinic',
            'visited_on' => '2026-10-11',
            'covered_amount' => 850,
        ];
    }

    // A single-day sick leave has the same start and end date.
    public function test_sick_leave_can_start_and_end_on_same_day(): void
    {
        $payload = array_merge($this->visitPayload(), [
            'sick_leave_started_on' => '2026-10-11',
            'sick_leave_ended_on' => '2026-10-11',
        ]);

        $response = $this->actingAs($this->employee, 'api')
            ->postJson('/api/visits', $payload)
            ->assertStatus(201)
            ->assertJsonPath('visit.sick_leave_started_on', '2026-10-11')
            ->assertJsonPath('visit.sick_leave_ended_on', '2026-10-11');

        $this->assertDatabaseHas('visits', [
            'id' => $response->json('visit.id'),
            'sick_leave_started_on' => '2026-10-11',
            'sick_leave_ended_on' => '2026-10-11',
        ]);
    }

    // Save visit time and doctor details, then verify authorized readers.
    public function test_time_and_doctor_details_are_saved_and_visible(): void
    {
        $payload = array_merge($this->visitPayload(), [
            'visited_time' => '14:30',
            'doctor_name' => 'Dr. Chen',
            'doctor_title' => 'General Practitioner',
        ]);

        $response = $this->actingAs($this->employee, 'api')
            ->postJson('/api/visits', $payload)
            ->assertStatus(201)
            ->assertJsonPath('visit.visited_time', '14:30')
            ->assertJsonPath('visit.doctor_name', 'Dr. Chen')
            ->assertJsonPath('visit.doctor_title', 'General Practitioner');

        $this->assertDatabaseHas('visits', [
            'id' => $response->json('visit.id'),
            'user_id' => $this->employee->id,
            'doctor_name' => 'Dr. Chen',
            'doctor_title' => 'General Practitioner',
        ]);

        $employeeResponse = $this->getJson('/api/visits')
            ->assertStatus(200)
            ->assertJsonPath('visits.0.doctor_name', 'Dr. Chen')
            ->assertJsonPath('visits.0.doctor_title', 'General Practitioner');

        // MySQL may return seconds; SQLite preserves the submitted HH:MM value.
        $this->assertSame(
            '14:30',
            substr($employeeResponse->json('visits.0.visited_time'), 0, 5)
        );

        $hrResponse = $this->actingAs($this->hr, 'api')
            ->getJson("/api/hr/employees/{$this->employee->id}/visits")
            ->assertStatus(200)
            ->assertJsonPath('visits.0.doctor_name', 'Dr. Chen')
            ->assertJsonPath('visits.0.doctor_title', 'General Practitioner');

        $this->assertSame(
            '14:30',
            substr($hrResponse->json('visits.0.visited_time'), 0, 5)
        );
    }

    // Unknown time and doctor details must remain null.
    public function test_time_and_doctor_details_can_be_omitted(): void
    {
        $response = $this->actingAs($this->employee, 'api')
            ->postJson('/api/visits', $this->visitPayload())
            ->assertStatus(201)
            ->assertJsonPath('visit.visited_time', null)
            ->assertJsonPath('visit.doctor_name', null)
            ->assertJsonPath('visit.doctor_title', null);

        $this->assertDatabaseHas('visits', [
            'id' => $response->json('visit.id'),
            'visited_time' => null,
            'doctor_name' => null,
            'doctor_title' => null,
        ]);
    }

    // Reject impossible times and formats other than HH:MM.
    public function test_visit_time_rejects_invalid_values(): void
    {
        foreach (['24:00', '14:60', '14:30:00', 'afternoon'] as $time) {
            $this->actingAs($this->employee, 'api')
                ->postJson('/api/visits', array_merge($this->visitPayload(), [
                    'visited_time' => $time,
                ]))
                ->assertStatus(422)
                ->assertJsonValidationErrors('visited_time');
        }

        $this->assertDatabaseCount('visits', 0);
    }

    // Reject non-string and oversized doctor details before saving.
    public function test_doctor_details_reject_invalid_values(): void
    {
        foreach (['doctor_name', 'doctor_title'] as $field) {
            foreach ([123, str_repeat('A', 256)] as $value) {
                $this->actingAs($this->employee, 'api')
                    ->postJson('/api/visits', array_merge($this->visitPayload(), [
                        $field => $value,
                    ]))
                    ->assertStatus(422)
                    ->assertJsonValidationErrors($field);
            }
        }

        $this->assertDatabaseCount('visits', 0);
    }
}