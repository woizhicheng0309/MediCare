<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyVisitRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_visit_form_is_unavailable(): void
    {
        // Visit submission now uses the authenticated API through React.
        $this->get('/visits/create')->assertNotFound();
    }

    public function test_legacy_submission_cannot_create_a_visit(): void
    {
        // A valid payload must not bypass authentication through the old route.
        $this->post('/visits', [
            'clinic' => 'Central Clinic',
            'visited_on' => '2026-10-11',
            'covered_amount' => 850,
        ])->assertNotFound();

        $this->assertDatabaseCount('visits', 0);
    }
}