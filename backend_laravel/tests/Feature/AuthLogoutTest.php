<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthLogoutTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */
    public function test_logout_invalidates_token(): void
    {
        $user = new User();
        $user->name = 'Alex Chen';
        $user->email = 'alex@example.test';
        $user->password = Hash::make('LearningDemo!2026');
        $user->role = 'employee';
        $user->save();

        // condition for the test unit to pass
        $this->assertDatabaseHas('users', [
            'email' => 'alex@example.test',
            'role' => 'employee',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'alex@example.test',
            'password' => 'LearningDemo!2026',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'access_token',
            'token_type',
        ]);

        $token = $response->json('access_token');

        $this->withToken($token)
        ->getJson('/api/me')
        ->assertStatus(200)
        ->assertJsonPath('id', $user->id);

        $this->withToken($token)
        ->postJson('/api/logout')
        ->assertStatus(200)
        ->assertJsonPath('message', 'Logged out successfully.');

        app('auth')->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertStatus(401);
    }
}
