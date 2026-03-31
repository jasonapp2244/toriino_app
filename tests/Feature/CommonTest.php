<?php

namespace Tests\Feature;

use App\Models\AiChat;
use App\Models\AppNotification;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseLevel;
use App\Models\PrivacyPolicy;
use App\Models\Review;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CommonTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->user = User::factory()->create([
            'is_verified' => true,
            'status'      => 'active',
            'role'        => 'student',
        ]);
        $this->user->assignRole('student');
        $this->headers = ['Authorization' => 'Bearer ' . $this->user->createToken('test')->plainTextToken];
    }

    // ═══════════════════════════════════════════════════════════════
    // NOTIFICATIONS
    // ═══════════════════════════════════════════════════════════════

    public function test_list_notifications(): void
    {
        AppNotification::create([
            'user_id' => $this->user->id,
            'title'   => 'Welcome',
            'body'    => 'Welcome to Torrino',
            'type'    => 'general',
        ]);

        $response = $this->getJson('/api/v1/notifications', $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonStructure(['data' => ['unread_count', 'notifications']]);
    }

    public function test_mark_notification_as_read(): void
    {
        $notification = AppNotification::create([
            'user_id' => $this->user->id,
            'title'   => 'Test',
            'body'    => 'Test body',
            'type'    => 'general',
        ]);

        $response = $this->postJson("/api/v1/notifications/{$notification->id}/read", [], $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true]);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_all_notifications_as_read(): void
    {
        AppNotification::create([
            'user_id' => $this->user->id, 'title' => 'N1', 'body' => 'B1', 'type' => 'general',
        ]);
        AppNotification::create([
            'user_id' => $this->user->id, 'title' => 'N2', 'body' => 'B2', 'type' => 'general',
        ]);

        $response = $this->postJson('/api/v1/notifications/read-all', [], $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true]);

        $unread = AppNotification::where('user_id', $this->user->id)->whereNull('read_at')->count();
        $this->assertEquals(0, $unread);
    }

    public function test_delete_notification(): void
    {
        $notification = AppNotification::create([
            'user_id' => $this->user->id, 'title' => 'Delete me', 'body' => 'Body', 'type' => 'general',
        ]);

        $response = $this->deleteJson("/api/v1/notifications/{$notification->id}", [], $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true]);

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_delete_other_users_notification_returns_404(): void
    {
        $other = User::factory()->create(['is_verified' => true, 'status' => 'active', 'role' => 'student']);
        $other->assignRole('student');

        $notification = AppNotification::create([
            'user_id' => $other->id, 'title' => 'Other', 'body' => 'Body', 'type' => 'general',
        ]);

        $response = $this->deleteJson("/api/v1/notifications/{$notification->id}", [], $this->headers);

        $response->assertStatus(404);
    }

    public function test_notifications_require_auth(): void
    {
        $response = $this->getJson('/api/v1/notifications');

        $response->assertStatus(401);
    }

    // ═══════════════════════════════════════════════════════════════
    // AI CHAT
    // ═══════════════════════════════════════════════════════════════

    public function test_ai_chat_ask(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'This is a test answer from AI.']],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/ai-chat', [
            'question' => 'What is Laravel?',
        ], $this->headers);

        $response->assertStatus(201)
            ->assertJson(['status' => true, 'message' => 'Answer received']);

        $this->assertDatabaseHas('ai_chats', [
            'user_id'  => $this->user->id,
            'question' => 'What is Laravel?',
        ]);
    }

    public function test_ai_chat_history(): void
    {
        AiChat::create([
            'user_id'  => $this->user->id,
            'question' => 'What is PHP?',
            'answer'   => 'PHP is a scripting language.',
        ]);

        $response = $this->getJson('/api/v1/ai-chat', $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true]);
    }

    public function test_ai_chat_clear_history(): void
    {
        AiChat::create([
            'user_id'  => $this->user->id,
            'question' => 'Test',
            'answer'   => 'Answer',
        ]);

        $response = $this->deleteJson('/api/v1/ai-chat', [], $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true, 'message' => 'Chat history cleared']);

        $this->assertEquals(0, AiChat::where('user_id', $this->user->id)->count());
    }

    public function test_ai_chat_validation_fails(): void
    {
        $response = $this->postJson('/api/v1/ai-chat', [], $this->headers);

        $response->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════════
    // SUPPORT TICKETS
    // ═══════════════════════════════════════════════════════════════

    public function test_list_support_tickets(): void
    {
        SupportTicket::create([
            'user_id' => $this->user->id,
            'subject' => 'Help',
            'message' => 'I need help',
        ]);

        $response = $this->getJson('/api/v1/support', $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true]);
    }

    public function test_create_support_ticket(): void
    {
        $response = $this->postJson('/api/v1/support', [
            'subject' => 'Bug Report',
            'message' => 'Found a bug in the app',
        ], $this->headers);

        $response->assertStatus(201)
            ->assertJson(['status' => true, 'message' => 'Support ticket submitted']);

        $this->assertDatabaseHas('support_tickets', [
            'user_id' => $this->user->id,
            'subject' => 'Bug Report',
        ]);
    }

    public function test_create_support_ticket_validation(): void
    {
        $response = $this->postJson('/api/v1/support', [], $this->headers);

        $response->assertStatus(422);
    }

    public function test_show_support_ticket(): void
    {
        $ticket = SupportTicket::create([
            'user_id' => $this->user->id,
            'subject' => 'Help',
            'message' => 'I need help',
        ]);

        $response = $this->getJson("/api/v1/support/{$ticket->id}", $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonPath('data.subject', 'Help');
    }

    public function test_close_support_ticket(): void
    {
        $ticket = SupportTicket::create([
            'user_id' => $this->user->id,
            'subject' => 'Help',
            'message' => 'I need help',
            'status'  => 'open',
        ]);

        $response = $this->postJson("/api/v1/support/{$ticket->id}/close", [], $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true]);

        $this->assertDatabaseHas('support_tickets', [
            'id'     => $ticket->id,
            'status' => 'closed',
        ]);
    }

    public function test_close_already_closed_ticket_fails(): void
    {
        $ticket = SupportTicket::create([
            'user_id' => $this->user->id,
            'subject' => 'Help',
            'message' => 'I need help',
            'status'  => 'closed',
        ]);

        $response = $this->postJson("/api/v1/support/{$ticket->id}/close", [], $this->headers);

        $response->assertStatus(400)
            ->assertJson(['status' => false]);
    }

    public function test_show_other_users_ticket_returns_404(): void
    {
        $other = User::factory()->create(['is_verified' => true, 'status' => 'active', 'role' => 'student']);
        $other->assignRole('student');

        $ticket = SupportTicket::create([
            'user_id' => $other->id,
            'subject' => 'Other',
            'message' => 'Other user ticket',
        ]);

        $response = $this->getJson("/api/v1/support/{$ticket->id}", $this->headers);

        $response->assertStatus(404);
    }

    // ═══════════════════════════════════════════════════════════════
    // REVIEWS
    // ═══════════════════════════════════════════════════════════════

    public function test_submit_review_for_user(): void
    {
        $mentor = User::factory()->create([
            'is_verified' => true, 'status' => 'active', 'role' => 'mentor',
        ]);
        $mentor->assignRole('mentor');
        $mentor->mentorProfile()->create(['user_id' => $mentor->id]);

        $response = $this->postJson('/api/v1/reviews', [
            'to_user_id' => $mentor->id,
            'rating'     => 5,
            'comment'    => 'Excellent mentor!',
        ], $this->headers);

        $response->assertStatus(201)
            ->assertJson(['status' => true, 'message' => 'Review submitted']);

        $this->assertDatabaseHas('reviews', [
            'from_user_id' => $this->user->id,
            'to_user_id'   => $mentor->id,
            'rating'       => 5,
        ]);
    }

    public function test_submit_review_for_course(): void
    {
        $category = CourseCategory::create(['name' => 'Tech', 'slug' => 'tech', 'is_active' => true]);
        $level = CourseLevel::create(['name' => 'Beginner', 'slug' => 'beginner', 'is_active' => true]);

        $teacher = User::factory()->create([
            'is_verified' => true, 'status' => 'active', 'role' => 'teacher',
        ]);
        $teacher->assignRole('teacher');

        $course = Course::create([
            'teacher_id'  => $teacher->id,
            'title'       => 'Test Course',
            'description' => 'Desc',
            'category_id' => $category->id,
            'level_id'    => $level->id,
            'language'    => 'en',
            'price'       => 10,
            'status'      => 'published',
        ]);

        $response = $this->postJson('/api/v1/reviews', [
            'course_id'  => $course->id,
            'to_user_id' => $teacher->id,
            'rating'     => 4,
            'comment'    => 'Great course!',
        ], $this->headers);

        $response->assertStatus(201)
            ->assertJson(['status' => true]);
    }

    public function test_submit_duplicate_review_fails(): void
    {
        $mentor = User::factory()->create([
            'is_verified' => true, 'status' => 'active', 'role' => 'mentor',
        ]);
        $mentor->assignRole('mentor');

        Review::create([
            'from_user_id' => $this->user->id,
            'to_user_id'   => $mentor->id,
            'rating'       => 4,
        ]);

        $response = $this->postJson('/api/v1/reviews', [
            'to_user_id' => $mentor->id,
            'rating'     => 5,
        ], $this->headers);

        $response->assertStatus(400)
            ->assertJson(['status' => false]);
    }

    public function test_review_validation_fails(): void
    {
        $response = $this->postJson('/api/v1/reviews', [], $this->headers);

        $response->assertStatus(422);
    }

    public function test_review_rating_must_be_between_1_and_5(): void
    {
        $mentor = User::factory()->create([
            'is_verified' => true, 'status' => 'active', 'role' => 'mentor',
        ]);
        $mentor->assignRole('mentor');

        $response = $this->postJson('/api/v1/reviews', [
            'to_user_id' => $mentor->id,
            'rating'     => 6,
        ], $this->headers);

        $response->assertStatus(422);
    }

    public function test_get_reviews_for_user_public(): void
    {
        $mentor = User::factory()->create([
            'is_verified' => true, 'status' => 'active', 'role' => 'mentor',
        ]);
        $mentor->assignRole('mentor');

        Review::create([
            'from_user_id' => $this->user->id,
            'to_user_id'   => $mentor->id,
            'rating'       => 5,
            'comment'      => 'Great!',
        ]);

        $response = $this->getJson("/api/v1/reviews/user/{$mentor->id}");

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonStructure(['data' => ['rating_average', 'total_reviews', 'reviews']]);
    }

    // ═══════════════════════════════════════════════════════════════
    // PRIVACY POLICY
    // ═══════════════════════════════════════════════════════════════

    public function test_privacy_policy_when_exists(): void
    {
        PrivacyPolicy::create([
            'title'     => 'Privacy Policy',
            'content'   => 'Our privacy policy content...',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/privacy-policy');

        $response->assertStatus(200)
            ->assertJson(['status' => true]);
    }

    public function test_privacy_policy_returns_404_when_none(): void
    {
        $response = $this->getJson('/api/v1/privacy-policy');

        $response->assertStatus(404);
    }

    // ═══════════════════════════════════════════════════════════════
    // SEARCH
    // ═══════════════════════════════════════════════════════════════

    public function test_search_requires_query(): void
    {
        $response = $this->getJson('/api/v1/search');

        $response->assertStatus(422);
    }

    public function test_search_returns_results(): void
    {
        $mentor = User::factory()->create([
            'is_verified' => true, 'status' => 'active', 'role' => 'mentor', 'name' => 'John Mentor',
        ]);
        $mentor->assignRole('mentor');

        $response = $this->getJson('/api/v1/search?q=John');

        $response->assertStatus(200)
            ->assertJson(['status' => true]);
    }

    public function test_search_with_type_filter(): void
    {
        $response = $this->getJson('/api/v1/search?q=test&type=course');

        $response->assertStatus(200)
            ->assertJson(['status' => true]);
    }

    // ═══════════════════════════════════════════════════════════════
    // API TEST ENDPOINT
    // ═══════════════════════════════════════════════════════════════

    public function test_api_test_endpoint(): void
    {
        $response = $this->getJson('/api/v1/api-test');

        $response->assertStatus(200)
            ->assertJson(['message' => 'API is working']);
    }
}
