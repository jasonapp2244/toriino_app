<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentorSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private User $mentor;
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->mentor = User::factory()->create([
            'is_verified' => true,
            'status'      => 'active',
            'role'        => 'mentor',
        ]);
        $this->mentor->assignRole('mentor');
        $this->mentor->mentorProfile()->create(['user_id' => $this->mentor->id]);
        $this->headers = ['Authorization' => 'Bearer ' . $this->mentor->createToken('test')->plainTextToken];
    }

    // ═══════════════════════════════════════════════════════════════
    // VIEW SUBSCRIPTION
    // ═══════════════════════════════════════════════════════════════

    public function test_view_subscription_without_active_plan(): void
    {
        $response = $this->getJson('/api/v1/mentor/subscription', $this->headers);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data'   => [
                    'has_active_subscription' => false,
                    'active_plan'             => null,
                ],
            ])
            ->assertJsonStructure(['data' => ['available_plans']]);
    }

    public function test_view_subscription_with_active_plan(): void
    {
        Subscription::create([
            'user_id'    => $this->mentor->id,
            'plan'       => 'monthly',
            'price'      => 9.99,
            'starts_at'  => now(),
            'expires_at' => now()->addDays(30),
            'status'     => 'active',
        ]);

        $response = $this->getJson('/api/v1/mentor/subscription', $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('data.has_active_subscription', true)
            ->assertJsonPath('data.active_plan.plan', 'monthly');
    }

    // ═══════════════════════════════════════════════════════════════
    // SUBSCRIBE
    // ═══════════════════════════════════════════════════════════════

    public function test_subscribe_monthly_plan(): void
    {
        $response = $this->postJson('/api/v1/mentor/subscription', [
            'plan' => 'monthly',
        ], $this->headers);

        $response->assertStatus(201)
            ->assertJson([
                'status' => true,
                'data'   => [
                    'has_active_subscription' => true,
                    'can_create_sessions'     => true,
                ],
            ]);

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $this->mentor->id,
            'plan'    => 'monthly',
            'status'  => 'active',
        ]);
    }

    public function test_subscribe_quarterly_plan(): void
    {
        $response = $this->postJson('/api/v1/mentor/subscription', [
            'plan' => 'quarterly',
        ], $this->headers);

        $response->assertStatus(201)
            ->assertJsonPath('data.subscription.plan', 'quarterly');
    }

    public function test_subscribe_annual_plan(): void
    {
        $response = $this->postJson('/api/v1/mentor/subscription', [
            'plan' => 'annual',
        ], $this->headers);

        $response->assertStatus(201)
            ->assertJsonPath('data.subscription.plan', 'annual');
    }

    public function test_subscribe_cancels_previous_active_plan(): void
    {
        $old = Subscription::create([
            'user_id'    => $this->mentor->id,
            'plan'       => 'monthly',
            'price'      => 9.99,
            'starts_at'  => now(),
            'expires_at' => now()->addDays(30),
            'status'     => 'active',
        ]);

        $response = $this->postJson('/api/v1/mentor/subscription', [
            'plan' => 'quarterly',
        ], $this->headers);

        $response->assertStatus(201);

        $this->assertDatabaseHas('subscriptions', [
            'id'     => $old->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_subscribe_fails_with_invalid_plan(): void
    {
        $response = $this->postJson('/api/v1/mentor/subscription', [
            'plan' => 'lifetime',
        ], $this->headers);

        $response->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════════
    // CANCEL
    // ═══════════════════════════════════════════════════════════════

    public function test_cancel_subscription_success(): void
    {
        Subscription::create([
            'user_id'    => $this->mentor->id,
            'plan'       => 'monthly',
            'price'      => 9.99,
            'starts_at'  => now(),
            'expires_at' => now()->addDays(30),
            'status'     => 'active',
        ]);

        $response = $this->deleteJson('/api/v1/mentor/subscription', [], $this->headers);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'data'   => [
                    'has_active_subscription' => false,
                    'can_create_sessions'     => false,
                ],
            ]);
    }

    public function test_cancel_fails_without_active_subscription(): void
    {
        $response = $this->deleteJson('/api/v1/mentor/subscription', [], $this->headers);

        $response->assertStatus(400)
            ->assertJson(['status' => false]);
    }

    // ═══════════════════════════════════════════════════════════════
    // CHECK ACTIVE STATUS (via session creation)
    // ═══════════════════════════════════════════════════════════════

    public function test_session_creation_blocked_without_subscription(): void
    {
        $response = $this->postJson('/api/v1/mentor/sessions', [
            'title'            => 'Test',
            'type'             => 'individual',
            'start_time'       => now()->addDays(1)->toIso8601String(),
            'end_time'         => now()->addDays(1)->addHour()->toIso8601String(),
            'max_seats'        => 1,
            'language'         => 'en',
            'price'            => 20,
            'duration_minutes' => 60,
        ], $this->headers);

        $response->assertStatus(402);
    }

    public function test_session_creation_allowed_with_subscription(): void
    {
        Subscription::create([
            'user_id'    => $this->mentor->id,
            'plan'       => 'monthly',
            'price'      => 9.99,
            'starts_at'  => now(),
            'expires_at' => now()->addDays(30),
            'status'     => 'active',
        ]);

        $response = $this->postJson('/api/v1/mentor/sessions', [
            'title'            => 'Allowed Session',
            'type'             => 'individual',
            'start_time'       => now()->addDays(1)->toIso8601String(),
            'end_time'         => now()->addDays(1)->addHour()->toIso8601String(),
            'max_seats'        => 1,
            'language'         => 'en',
            'price'            => 20,
            'duration_minutes' => 60,
        ], $this->headers);

        $response->assertStatus(201);
    }

    // ═══════════════════════════════════════════════════════════════
    // ROLE ENFORCEMENT
    // ═══════════════════════════════════════════════════════════════

    public function test_student_cannot_access_mentor_subscription(): void
    {
        $student = User::factory()->create([
            'is_verified' => true, 'status' => 'active', 'role' => 'student',
        ]);
        $student->assignRole('student');
        $token = $student->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/v1/mentor/subscription', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(403);
    }
}
