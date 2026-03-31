<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private User $userB;
    private array $headersA;
    private array $headersB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->userA = User::factory()->create([
            'is_verified' => true, 'status' => 'active', 'role' => 'student',
        ]);
        $this->userA->assignRole('student');
        $this->headersA = ['Authorization' => 'Bearer ' . $this->userA->createToken('test')->plainTextToken];

        $this->userB = User::factory()->create([
            'is_verified' => true, 'status' => 'active', 'role' => 'mentor',
        ]);
        $this->userB->assignRole('mentor');
        $this->headersB = ['Authorization' => 'Bearer ' . $this->userB->createToken('test')->plainTextToken];
    }

    // ═══════════════════════════════════════════════════════════════
    // LIST CONVERSATIONS
    // ═══════════════════════════════════════════════════════════════

    public function test_list_conversations_empty(): void
    {
        $response = $this->getJson('/api/v1/chat/conversations', $this->headersA);

        $response->assertStatus(200)
            ->assertJson(['status' => true]);
    }

    public function test_list_conversations_with_existing(): void
    {
        $conversation = Conversation::between($this->userA->id, $this->userB->id);

        $response = $this->getJson('/api/v1/chat/conversations', $this->headersA);

        $response->assertStatus(200)
            ->assertJson(['status' => true]);

        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    public function test_list_conversations_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/chat/conversations');

        $response->assertStatus(401);
    }

    // ═══════════════════════════════════════════════════════════════
    // START CONVERSATION
    // ═══════════════════════════════════════════════════════════════

    public function test_start_conversation_success(): void
    {
        $response = $this->postJson('/api/v1/chat/conversations', [
            'user_id' => $this->userB->id,
        ], $this->headersA);

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonStructure(['data' => ['id', 'other_user', 'channel']]);

        $this->assertDatabaseHas('conversations', [
            'participant_one_id' => min($this->userA->id, $this->userB->id),
            'participant_two_id' => max($this->userA->id, $this->userB->id),
        ]);
    }

    public function test_start_conversation_returns_existing(): void
    {
        $existing = Conversation::between($this->userA->id, $this->userB->id);

        $response = $this->postJson('/api/v1/chat/conversations', [
            'user_id' => $this->userB->id,
        ], $this->headersA);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $existing->id);
    }

    public function test_start_conversation_with_self_fails(): void
    {
        $response = $this->postJson('/api/v1/chat/conversations', [
            'user_id' => $this->userA->id,
        ], $this->headersA);

        $response->assertStatus(422)
            ->assertJson(['status' => false]);
    }

    public function test_start_conversation_validation_fails(): void
    {
        $response = $this->postJson('/api/v1/chat/conversations', [
            'user_id' => 99999,
        ], $this->headersA);

        $response->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════════
    // SEND MESSAGE
    // ═══════════════════════════════════════════════════════════════

    public function test_send_message_success(): void
    {
        Event::fake();

        $conversation = Conversation::between($this->userA->id, $this->userB->id);

        $response = $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
            'message' => 'Hello, mentor!',
        ], $this->headersA);

        $response->assertStatus(201)
            ->assertJson(['status' => true, 'message' => 'Message sent.']);

        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conversation->id,
            'sender_id'       => $this->userA->id,
            'message'         => 'Hello, mentor!',
        ]);
    }

    public function test_send_message_to_nonexistent_conversation(): void
    {
        $response = $this->postJson('/api/v1/chat/conversations/99999/messages', [
            'message' => 'Hello',
        ], $this->headersA);

        $response->assertStatus(404);
    }

    public function test_send_message_validation_fails(): void
    {
        $conversation = Conversation::between($this->userA->id, $this->userB->id);

        $response = $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [], $this->headersA);

        $response->assertStatus(422);
    }

    public function test_send_message_with_type(): void
    {
        Event::fake();

        $conversation = Conversation::between($this->userA->id, $this->userB->id);

        $response = $this->postJson("/api/v1/chat/conversations/{$conversation->id}/messages", [
            'message'        => 'Check this image',
            'type'           => 'image',
            'attachment_url' => 'https://example.com/image.jpg',
        ], $this->headersA);

        $response->assertStatus(201);

        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conversation->id,
            'type'            => 'image',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // MARK READ
    // ═══════════════════════════════════════════════════════════════

    public function test_mark_messages_as_read(): void
    {
        $conversation = Conversation::between($this->userA->id, $this->userB->id);

        // User B sends messages to User A
        ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $this->userB->id,
            'message'         => 'Hey there',
            'is_read'         => false,
        ]);
        ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $this->userB->id,
            'message'         => 'How are you?',
            'is_read'         => false,
        ]);

        // User A marks as read
        $response = $this->postJson("/api/v1/chat/conversations/{$conversation->id}/read", [], $this->headersA);

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonPath('data.marked_read', 2);
    }

    public function test_mark_read_for_nonexistent_conversation(): void
    {
        $response = $this->postJson('/api/v1/chat/conversations/99999/read', [], $this->headersA);

        $response->assertStatus(404);
    }

    // ═══════════════════════════════════════════════════════════════
    // DELETE CONVERSATION
    // ═══════════════════════════════════════════════════════════════

    public function test_delete_conversation(): void
    {
        $conversation = Conversation::between($this->userA->id, $this->userB->id);

        $response = $this->deleteJson("/api/v1/chat/conversations/{$conversation->id}", [], $this->headersA);

        $response->assertStatus(200)
            ->assertJson(['status' => true, 'message' => 'Conversation deleted.']);

        $this->assertDatabaseMissing('conversations', [
            'id' => $conversation->id,
        ]);
    }

    public function test_delete_nonexistent_conversation(): void
    {
        $response = $this->deleteJson('/api/v1/chat/conversations/99999', [], $this->headersA);

        $response->assertStatus(404);
    }

    public function test_cannot_delete_other_users_conversation(): void
    {
        $userC = User::factory()->create([
            'is_verified' => true, 'status' => 'active', 'role' => 'student',
        ]);
        $userC->assignRole('student');

        $conversation = Conversation::between($this->userB->id, $userC->id);

        $response = $this->deleteJson("/api/v1/chat/conversations/{$conversation->id}", [], $this->headersA);

        $response->assertStatus(404);
    }

    // ═══════════════════════════════════════════════════════════════
    // GET MESSAGES
    // ═══════════════════════════════════════════════════════════════

    public function test_get_messages_for_conversation(): void
    {
        $conversation = Conversation::between($this->userA->id, $this->userB->id);
        ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $this->userA->id,
            'message'         => 'First message',
        ]);

        $response = $this->getJson("/api/v1/chat/conversations/{$conversation->id}/messages", $this->headersA);

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonStructure(['data' => ['conversation_id', 'messages']]);
    }

    // ═══════════════════════════════════════════════════════════════
    // SEARCH USERS
    // ═══════════════════════════════════════════════════════════════

    public function test_search_users_for_chat(): void
    {
        $response = $this->getJson('/api/v1/chat/users/search?q=' . urlencode($this->userB->name), $this->headersA);

        $response->assertStatus(200)
            ->assertJson(['status' => true]);
    }

    public function test_search_users_validation_fails(): void
    {
        $response = $this->getJson('/api/v1/chat/users/search', $this->headersA);

        $response->assertStatus(422);
    }
}
