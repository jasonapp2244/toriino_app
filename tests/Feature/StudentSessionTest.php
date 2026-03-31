<?php

namespace Tests\Feature;

use App\Models\MentorSession;
use App\Models\SessionBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentSessionTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $mentor;
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->student = User::factory()->create([
            'is_verified' => true,
            'status'      => 'active',
            'role'        => 'student',
        ]);
        $this->student->assignRole('student');
        $this->headers = ['Authorization' => 'Bearer ' . $this->student->createToken('test')->plainTextToken];

        $this->mentor = User::factory()->create([
            'is_verified' => true,
            'status'      => 'active',
            'role'        => 'mentor',
        ]);
        $this->mentor->assignRole('mentor');
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
    // LIST SESSIONS
    // ═══════════════════════════════════════════════════════════════

    public function test_list_student_sessions(): void
    {
        $session = $this->createSession();
        SessionBooking::create([
            'session_id' => $session->id,
            'student_id' => $this->student->id,
            'status'     => 'confirmed',
        ]);

        $response = $this->getJson('/api/v1/student/sessions', $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true]);
    }

    public function test_list_sessions_filter_by_status(): void
    {
        $session = $this->createSession();
        SessionBooking::create([
            'session_id' => $session->id,
            'student_id' => $this->student->id,
            'status'     => 'confirmed',
        ]);

        $response = $this->getJson('/api/v1/student/sessions?status=confirmed', $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true]);
    }

    public function test_list_sessions_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/student/sessions');

        $response->assertStatus(401);
    }

    // ═══════════════════════════════════════════════════════════════
    // BOOK SESSION
    // ═══════════════════════════════════════════════════════════════

    public function test_book_session_success(): void
    {
        $session = $this->createSession();

        $response = $this->postJson('/api/v1/student/sessions/book', [
            'session_id' => $session->id,
        ], $this->headers);

        $response->assertStatus(201)
            ->assertJson(['status' => true, 'message' => 'Session booked successfully']);

        $this->assertDatabaseHas('session_bookings', [
            'session_id' => $session->id,
            'student_id' => $this->student->id,
            'status'     => 'confirmed',
        ]);
    }

    public function test_book_session_fails_when_no_seats(): void
    {
        $session = $this->createSession(['max_seats' => 1, 'seats_booked' => 1]);

        $response = $this->postJson('/api/v1/student/sessions/book', [
            'session_id' => $session->id,
        ], $this->headers);

        $response->assertStatus(400)
            ->assertJson(['status' => false]);
    }

    public function test_book_session_fails_when_already_booked(): void
    {
        $session = $this->createSession(['max_seats' => 3]);
        SessionBooking::create([
            'session_id' => $session->id,
            'student_id' => $this->student->id,
            'status'     => 'confirmed',
        ]);

        $response = $this->postJson('/api/v1/student/sessions/book', [
            'session_id' => $session->id,
        ], $this->headers);

        $response->assertStatus(400)
            ->assertJson(['status' => false]);
    }

    public function test_book_session_fails_when_not_upcoming(): void
    {
        $session = $this->createSession(['status' => 'completed']);

        $response = $this->postJson('/api/v1/student/sessions/book', [
            'session_id' => $session->id,
        ], $this->headers);

        $response->assertStatus(400)
            ->assertJson(['status' => false]);
    }

    public function test_book_session_validation_fails(): void
    {
        $response = $this->postJson('/api/v1/student/sessions/book', [
            'session_id' => 99999,
        ], $this->headers);

        $response->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════════
    // CANCEL SESSION
    // ═══════════════════════════════════════════════════════════════

    public function test_cancel_booking_success(): void
    {
        $session = $this->createSession();
        $session->increment('seats_booked');
        $booking = SessionBooking::create([
            'session_id' => $session->id,
            'student_id' => $this->student->id,
            'status'     => 'confirmed',
        ]);

        $response = $this->deleteJson("/api/v1/student/sessions/{$booking->id}", [], $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true, 'message' => 'Booking cancelled']);

        $this->assertDatabaseHas('session_bookings', [
            'id'     => $booking->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cancel_booking_not_found(): void
    {
        $response = $this->deleteJson('/api/v1/student/sessions/99999', [], $this->headers);

        $response->assertStatus(404);
    }

    public function test_cancel_already_cancelled_booking(): void
    {
        $session = $this->createSession();
        $booking = SessionBooking::create([
            'session_id' => $session->id,
            'student_id' => $this->student->id,
            'status'     => 'cancelled',
        ]);

        $response = $this->deleteJson("/api/v1/student/sessions/{$booking->id}", [], $this->headers);

        $response->assertStatus(422)
            ->assertJson(['status' => false]);
    }

    // ═══════════════════════════════════════════════════════════════
    // SESSION STATUS
    // ═══════════════════════════════════════════════════════════════

    public function test_session_status(): void
    {
        $session = $this->createSession(['status' => 'ongoing']);
        $booking = SessionBooking::create([
            'session_id' => $session->id,
            'student_id' => $this->student->id,
            'status'     => 'confirmed',
        ]);

        $response = $this->getJson("/api/v1/student/sessions/{$booking->id}/status", $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonPath('data.session_status', 'ongoing')
            ->assertJsonPath('data.can_join', true);
    }

    public function test_session_status_not_found(): void
    {
        $response = $this->getJson('/api/v1/student/sessions/99999/status', $this->headers);

        $response->assertStatus(404);
    }

    // ═══════════════════════════════════════════════════════════════
    // MEETING INFO
    // ═══════════════════════════════════════════════════════════════

    public function test_meeting_info_when_session_ongoing(): void
    {
        $session = $this->createSession(['status' => 'ongoing']);
        $booking = SessionBooking::create([
            'session_id' => $session->id,
            'student_id' => $this->student->id,
            'status'     => 'confirmed',
        ]);

        $response = $this->getJson("/api/v1/student/sessions/{$booking->id}/meeting-info", $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonStructure(['data' => ['meeting_room_id', 'jitsi_room']]);
    }

    public function test_meeting_info_fails_when_session_not_started(): void
    {
        $session = $this->createSession(['status' => 'upcoming']);
        $booking = SessionBooking::create([
            'session_id' => $session->id,
            'student_id' => $this->student->id,
            'status'     => 'confirmed',
        ]);

        $response = $this->getJson("/api/v1/student/sessions/{$booking->id}/meeting-info", $this->headers);

        $response->assertStatus(422)
            ->assertJson(['status' => false]);
    }

    // ═══════════════════════════════════════════════════════════════
    // SHOW BOOKING DETAIL
    // ═══════════════════════════════════════════════════════════════

    public function test_show_booking_detail(): void
    {
        $session = $this->createSession();
        $booking = SessionBooking::create([
            'session_id' => $session->id,
            'student_id' => $this->student->id,
            'status'     => 'confirmed',
        ]);

        $response = $this->getJson("/api/v1/student/sessions/{$booking->id}", $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonStructure(['data' => ['booking', 'session', 'mentor']]);
    }

    public function test_show_other_students_booking_returns_404(): void
    {
        $otherStudent = User::factory()->create([
            'is_verified' => true, 'status' => 'active', 'role' => 'student',
        ]);
        $otherStudent->assignRole('student');

        $session = $this->createSession();
        $booking = SessionBooking::create([
            'session_id' => $session->id,
            'student_id' => $otherStudent->id,
            'status'     => 'confirmed',
        ]);

        $response = $this->getJson("/api/v1/student/sessions/{$booking->id}", $this->headers);

        $response->assertStatus(404);
    }
}
