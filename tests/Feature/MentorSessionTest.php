<?php

namespace Tests\Feature;

use App\Models\MentorSession;
use App\Models\SessionBooking;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentorSessionTest extends TestCase
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
        $this->headers = ['Authorization' => 'Bearer ' . $this->mentor->createToken('test')->plainTextToken];
    }

    private function giveSubscription(): void
    {
        Subscription::create([
            'user_id'    => $this->mentor->id,
            'plan'       => 'monthly',
            'price'      => 9.99,
            'starts_at'  => now(),
            'expires_at' => now()->addDays(30),
            'status'     => 'active',
        ]);
    }

    private function createSession(array $overrides = []): MentorSession
    {
        return MentorSession::create(array_merge([
            'mentor_id'        => $this->mentor->id,
            'title'            => 'Test Session',
            'type'             => 'individual',
            'start_time'       => now()->addDays(1),
            'end_time'         => now()->addDays(1)->addHour(),
            'max_seats'        => 1,
            'language'         => 'en',
            'price'            => 30.00,
            'duration_minutes' => 60,
            'status'           => 'upcoming',
            'meeting_room_id'  => 'torrino-test-' . uniqid(),
        ], $overrides));
    }

    // ═══════════════════════════════════════════════════════════════
    // CREATE SESSION
    // ═══════════════════════════════════════════════════════════════

    public function test_create_session_with_subscription(): void
    {
        $this->giveSubscription();

        $response = $this->postJson('/api/v1/mentor/sessions', [
            'title'            => 'My Session',
            'type'             => 'individual',
            'start_time'       => now()->addDays(2)->toIso8601String(),
            'end_time'         => now()->addDays(2)->addHour()->toIso8601String(),
            'max_seats'        => 1,
            'language'         => 'en',
            'price'            => 25.00,
            'duration_minutes' => 60,
        ], $this->headers);

        $response->assertStatus(201)
            ->assertJson(['status' => true, 'message' => 'Session created']);

        $this->assertDatabaseHas('mentor_sessions', [
            'mentor_id' => $this->mentor->id,
            'title'     => 'My Session',
        ]);
    }

    public function test_create_session_fails_without_subscription(): void
    {
        $response = $this->postJson('/api/v1/mentor/sessions', [
            'title'            => 'My Session',
            'type'             => 'individual',
            'start_time'       => now()->addDays(2)->toIso8601String(),
            'end_time'         => now()->addDays(2)->addHour()->toIso8601String(),
            'max_seats'        => 1,
            'language'         => 'en',
            'price'            => 25.00,
            'duration_minutes' => 60,
        ], $this->headers);

        $response->assertStatus(402)
            ->assertJson(['status' => false])
            ->assertJsonPath('errors.subscription_required', true);
    }

    public function test_create_session_validation_fails(): void
    {
        $this->giveSubscription();

        $response = $this->postJson('/api/v1/mentor/sessions', [], $this->headers);

        $response->assertStatus(422);
    }

    public function test_create_group_session(): void
    {
        $this->giveSubscription();

        $response = $this->postJson('/api/v1/mentor/sessions', [
            'title'            => 'Group Session',
            'type'             => 'group',
            'start_time'       => now()->addDays(3)->toIso8601String(),
            'end_time'         => now()->addDays(3)->addHours(2)->toIso8601String(),
            'max_seats'        => 5,
            'language'         => 'en',
            'price'            => 15.00,
            'duration_minutes' => 120,
        ], $this->headers);

        $response->assertStatus(201)
            ->assertJson(['status' => true]);

        // Group sessions should have a join_key
        $this->assertNotNull($response->json('data.join_key'));
    }

    // ═══════════════════════════════════════════════════════════════
    // LIST SESSIONS
    // ═══════════════════════════════════════════════════════════════

    public function test_list_mentor_sessions(): void
    {
        $this->createSession();

        $response = $this->getJson('/api/v1/mentor/sessions', $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonStructure(['data' => ['stats', 'upcoming', 'history']]);
    }

    // ═══════════════════════════════════════════════════════════════
    // START SESSION
    // ═══════════════════════════════════════════════════════════════

    public function test_start_session_success(): void
    {
        $session = $this->createSession(['status' => 'upcoming']);

        $response = $this->postJson("/api/v1/mentor/sessions/{$session->id}/start", [], $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true, 'message' => 'Session started'])
            ->assertJsonStructure(['data' => ['meeting_room_id', 'jitsi_room']]);

        $this->assertDatabaseHas('mentor_sessions', [
            'id'     => $session->id,
            'status' => 'ongoing',
        ]);
    }

    public function test_start_session_fails_if_completed(): void
    {
        $session = $this->createSession(['status' => 'completed']);

        $response = $this->postJson("/api/v1/mentor/sessions/{$session->id}/start", [], $this->headers);

        $response->assertStatus(422)
            ->assertJson(['status' => false]);
    }

    public function test_start_session_not_found(): void
    {
        $response = $this->postJson('/api/v1/mentor/sessions/99999/start', [], $this->headers);

        $response->assertStatus(404);
    }

    // ═══════════════════════════════════════════════════════════════
    // COMPLETE SESSION
    // ═══════════════════════════════════════════════════════════════

    public function test_complete_session_success(): void
    {
        $session = $this->createSession(['status' => 'ongoing']);

        $response = $this->postJson("/api/v1/mentor/sessions/{$session->id}/complete", [], $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true, 'message' => 'Session completed']);

        $this->assertDatabaseHas('mentor_sessions', [
            'id'     => $session->id,
            'status' => 'completed',
        ]);
    }

    public function test_complete_session_fails_if_not_ongoing(): void
    {
        $session = $this->createSession(['status' => 'upcoming']);

        $response = $this->postJson("/api/v1/mentor/sessions/{$session->id}/complete", [], $this->headers);

        $response->assertStatus(422)
            ->assertJson(['status' => false]);
    }

    // ═══════════════════════════════════════════════════════════════
    // DELETE (CANCEL) SESSION
    // ═══════════════════════════════════════════════════════════════

    public function test_delete_session_success(): void
    {
        $session = $this->createSession();

        $response = $this->deleteJson("/api/v1/mentor/sessions/{$session->id}", [], $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true, 'message' => 'Session cancelled']);

        $this->assertDatabaseHas('mentor_sessions', [
            'id'     => $session->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_delete_session_not_found(): void
    {
        $response = $this->deleteJson('/api/v1/mentor/sessions/99999', [], $this->headers);

        $response->assertStatus(404);
    }

    public function test_cannot_delete_other_mentors_session(): void
    {
        $otherMentor = User::factory()->create([
            'is_verified' => true, 'status' => 'active', 'role' => 'mentor',
        ]);
        $otherMentor->assignRole('mentor');

        $session = MentorSession::create([
            'mentor_id'        => $otherMentor->id,
            'title'            => 'Other Session',
            'type'             => 'individual',
            'start_time'       => now()->addDays(1),
            'end_time'         => now()->addDays(1)->addHour(),
            'max_seats'        => 1,
            'language'         => 'en',
            'price'            => 30.00,
            'duration_minutes' => 60,
            'status'           => 'upcoming',
            'meeting_room_id'  => 'torrino-other-' . uniqid(),
        ]);

        $response = $this->deleteJson("/api/v1/mentor/sessions/{$session->id}", [], $this->headers);

        $response->assertStatus(404);
    }

    // ═══════════════════════════════════════════════════════════════
    // HEARTBEAT
    // ═══════════════════════════════════════════════════════════════

    public function test_heartbeat_success(): void
    {
        $session = $this->createSession(['status' => 'ongoing']);

        $response = $this->postJson("/api/v1/mentor/sessions/{$session->id}/heartbeat", [], $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true, 'message' => 'Heartbeat recorded.'])
            ->assertJsonStructure(['data' => ['session_id', 'status', 'pinged_at']]);
    }

    public function test_heartbeat_fails_for_non_ongoing_session(): void
    {
        $session = $this->createSession(['status' => 'upcoming']);

        $response = $this->postJson("/api/v1/mentor/sessions/{$session->id}/heartbeat", [], $this->headers);

        $response->assertStatus(404);
    }

    // ═══════════════════════════════════════════════════════════════
    // MEETING INFO
    // ═══════════════════════════════════════════════════════════════

    public function test_meeting_info(): void
    {
        $session = $this->createSession();

        $response = $this->getJson("/api/v1/mentor/sessions/{$session->id}/meeting-info", $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonStructure(['data' => ['meeting_room_id', 'jitsi_room', 'session_id']]);
    }

    // ═══════════════════════════════════════════════════════════════
    // ROLE ENFORCEMENT
    // ═══════════════════════════════════════════════════════════════

    public function test_student_cannot_access_mentor_session_routes(): void
    {
        $student = User::factory()->create([
            'is_verified' => true, 'status' => 'active', 'role' => 'student',
        ]);
        $student->assignRole('student');
        $token = $student->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/v1/mentor/sessions', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(403);
    }
}
