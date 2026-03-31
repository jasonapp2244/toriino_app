<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────

    private function createVerifiedUser(string $role = 'student', array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'is_verified' => true,
            'status'      => 'active',
            'role'        => $role,
        ], $overrides));

        $user->assignRole($role);
        return $user;
    }

    private function authHeader(User $user): array
    {
        $token = $user->createToken('test')->plainTextToken;
        return ['Authorization' => "Bearer $token"];
    }

    // ═══════════════════════════════════════════════════════════════
    // REGISTER
    // ═══════════════════════════════════════════════════════════════

    public function test_register_success(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/register', [
            'full_name' => 'John Doe',
            'email'     => 'john@example.com',
            'password'  => 'password123',
            'role'      => 'student',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => ['email', 'message'],
            ])
            ->assertJson(['status' => true]);

        $this->assertDatabaseHas('users', [
            'email'       => 'john@example.com',
            'is_verified' => false,
        ]);
    }

    public function test_register_validation_fails_without_required_fields(): void
    {
        $response = $this->postJson('/api/v1/register', []);

        $response->assertStatus(422)
            ->assertJson(['status' => false]);
    }

    public function test_register_fails_for_already_verified_email(): void
    {
        Queue::fake();

        $this->createVerifiedUser('student', ['email' => 'existing@example.com']);

        $response = $this->postJson('/api/v1/register', [
            'full_name' => 'Jane Doe',
            'email'     => 'existing@example.com',
            'password'  => 'password123',
        ]);

        $response->assertStatus(409)
            ->assertJson(['status' => false]);
    }

    // ═══════════════════════════════════════════════════════════════
    // OTP VERIFY
    // ═══════════════════════════════════════════════════════════════

    public function test_otp_verify_success(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'email'          => 'otp@example.com',
            'is_verified'    => false,
            'otp_code'       => '123456',
            'otp_expires_at' => now()->addMinutes(10),
            'role'           => 'student',
            'status'         => 'active',
        ]);

        $response = $this->postJson('/api/v1/otp-verify', [
            'email'    => 'otp@example.com',
            'otp_code' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonStructure([
                'data' => ['user', 'token'],
            ]);

        $this->assertDatabaseHas('users', [
            'email'       => 'otp@example.com',
            'is_verified' => true,
        ]);
    }

    public function test_otp_verify_fails_with_invalid_otp(): void
    {
        $user = User::factory()->create([
            'email'          => 'otp@example.com',
            'is_verified'    => false,
            'otp_code'       => '123456',
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/v1/otp-verify', [
            'email'    => 'otp@example.com',
            'otp_code' => '999999',
        ]);

        $response->assertStatus(422)
            ->assertJson(['status' => false]);
    }

    public function test_otp_verify_fails_with_expired_otp(): void
    {
        $user = User::factory()->create([
            'email'          => 'otp@example.com',
            'is_verified'    => false,
            'otp_code'       => '123456',
            'otp_expires_at' => now()->subMinutes(1),
        ]);

        $response = $this->postJson('/api/v1/otp-verify', [
            'email'    => 'otp@example.com',
            'otp_code' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJson(['status' => false]);
    }

    public function test_otp_verify_fails_for_already_verified_user(): void
    {
        $user = User::factory()->create([
            'email'          => 'otp@example.com',
            'is_verified'    => true,
            'otp_code'       => '123456',
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/v1/otp-verify', [
            'email'    => 'otp@example.com',
            'otp_code' => '123456',
        ]);

        $response->assertStatus(400)
            ->assertJson(['status' => false]);
    }

    // ═══════════════════════════════════════════════════════════════
    // LOGIN
    // ═══════════════════════════════════════════════════════════════

    public function test_login_success(): void
    {
        $user = $this->createVerifiedUser('student', [
            'email'    => 'login@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email'    => 'login@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => true, 'message' => 'Login successful.'])
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'full_name', 'email', 'role'],
                    'token',
                ],
            ]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = $this->createVerifiedUser('student', [
            'email'    => 'login@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email'    => 'login@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson(['status' => false]);
    }

    public function test_login_fails_for_unverified_user(): void
    {
        Queue::fake();

        User::factory()->create([
            'email'       => 'unverified@example.com',
            'password'    => Hash::make('password123'),
            'is_verified' => false,
            'status'      => 'active',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email'    => 'unverified@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson(['status' => false]);
    }

    public function test_login_fails_for_banned_user(): void
    {
        $user = $this->createVerifiedUser('student', [
            'email'    => 'banned@example.com',
            'password' => Hash::make('password123'),
            'status'   => 'banned',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email'    => 'banned@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson(['status' => false]);
    }

    public function test_login_validation_fails(): void
    {
        $response = $this->postJson('/api/v1/login', []);

        $response->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════════
    // LOGOUT
    // ═══════════════════════════════════════════════════════════════

    public function test_logout_success(): void
    {
        $user = $this->createVerifiedUser();

        $response = $this->postJson('/api/v1/logout', [], $this->authHeader($user));

        $response->assertStatus(200)
            ->assertJson(['status' => true, 'message' => 'Logged out successfully.']);
    }

    public function test_logout_fails_without_token(): void
    {
        $response = $this->postJson('/api/v1/logout');

        $response->assertStatus(401);
    }

    // ═══════════════════════════════════════════════════════════════
    // ME
    // ═══════════════════════════════════════════════════════════════

    public function test_me_returns_authenticated_user(): void
    {
        $user = $this->createVerifiedUser('student');

        $response = $this->getJson('/api/v1/me', $this->authHeader($user));

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonStructure([
                'data' => ['id', 'full_name', 'email', 'role', 'is_verified', 'status'],
            ]);
    }

    public function test_me_fails_without_auth(): void
    {
        $response = $this->getJson('/api/v1/me');

        $response->assertStatus(401);
    }

    // ═══════════════════════════════════════════════════════════════
    // FORGOT PASSWORD
    // ═══════════════════════════════════════════════════════════════

    public function test_forgot_password_sends_otp(): void
    {
        Queue::fake();

        $user = $this->createVerifiedUser('student', ['email' => 'forgot@example.com']);

        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => 'forgot@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonStructure(['data' => ['email']]);
    }

    public function test_forgot_password_fails_for_unknown_email(): void
    {
        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => 'unknown@example.com',
        ]);

        $response->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════════
    // RESET PASSWORD
    // ═══════════════════════════════════════════════════════════════

    public function test_reset_password_success(): void
    {
        $user = $this->createVerifiedUser('student', [
            'email'          => 'reset@example.com',
            'otp_code'       => '654321',
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/v1/reset-password', [
            'email'                 => 'reset@example.com',
            'otp_code'              => '654321',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => true]);
    }

    public function test_reset_password_fails_with_wrong_otp(): void
    {
        $user = $this->createVerifiedUser('student', [
            'email'          => 'reset@example.com',
            'otp_code'       => '654321',
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/v1/reset-password', [
            'email'                 => 'reset@example.com',
            'otp_code'              => '000000',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJson(['status' => false]);
    }

    // ═══════════════════════════════════════════════════════════════
    // CHANGE PASSWORD
    // ═══════════════════════════════════════════════════════════════

    public function test_change_password_success(): void
    {
        $user = $this->createVerifiedUser('student', [
            'password' => Hash::make('oldpassword123'),
        ]);

        $response = $this->postJson('/api/v1/change-password', [
            'current_password'          => 'oldpassword123',
            'new_password'              => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ], $this->authHeader($user));

        $response->assertStatus(200)
            ->assertJson(['status' => true]);
    }

    public function test_change_password_fails_with_wrong_current_password(): void
    {
        $user = $this->createVerifiedUser('student', [
            'password' => Hash::make('oldpassword123'),
        ]);

        $response = $this->postJson('/api/v1/change-password', [
            'current_password'          => 'wrongpassword',
            'new_password'              => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ], $this->authHeader($user));

        $response->assertStatus(422)
            ->assertJson(['status' => false]);
    }

    public function test_change_password_fails_when_same_as_current(): void
    {
        $user = $this->createVerifiedUser('student', [
            'password' => Hash::make('samepassword123'),
        ]);

        $response = $this->postJson('/api/v1/change-password', [
            'current_password'          => 'samepassword123',
            'new_password'              => 'samepassword123',
            'new_password_confirmation' => 'samepassword123',
        ], $this->authHeader($user));

        $response->assertStatus(422)
            ->assertJson(['status' => false]);
    }
}
