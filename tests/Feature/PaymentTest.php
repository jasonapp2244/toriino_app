<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseEnrollment;
use App\Models\CourseLevel;
use App\Models\MentorSession;
use App\Models\Payment;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $teacher;
    private User $mentor;
    private CourseCategory $category;
    private CourseLevel $level;
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->student = User::factory()->create([
            'is_verified' => true, 'status' => 'active', 'role' => 'student',
        ]);
        $this->student->assignRole('student');
        $this->headers = ['Authorization' => 'Bearer ' . $this->student->createToken('test')->plainTextToken];

        $this->teacher = User::factory()->create([
            'is_verified' => true, 'status' => 'active', 'role' => 'teacher',
        ]);
        $this->teacher->assignRole('teacher');

        $this->mentor = User::factory()->create([
            'is_verified' => true, 'status' => 'active', 'role' => 'mentor',
        ]);
        $this->mentor->assignRole('mentor');

        $this->category = CourseCategory::create(['name' => 'Tech', 'slug' => 'tech', 'is_active' => true]);
        $this->level = CourseLevel::create(['name' => 'Beginner', 'slug' => 'beginner', 'is_active' => true]);
    }

    private function mockStripeService(): void
    {
        $mock = Mockery::mock(StripeService::class)->shouldIgnoreMissing();
        $mock->shouldReceive('createPaymentIntent')
            ->andReturnUsing(function () {
                $pi = new \stdClass();
                $pi->id = 'pi_test_123';
                $pi->client_secret = 'cs_test_secret';
                return $pi;
            });
        $mock->shouldReceive('retrievePaymentIntent')
            ->andReturnUsing(function () {
                $pi = new \stdClass();
                $pi->id = 'pi_test_123';
                $pi->status = 'succeeded';
                return $pi;
            });
        $this->app->instance(StripeService::class, $mock);
    }

    private function createPublishedCourse(float $price = 29.99): Course
    {
        return Course::create([
            'teacher_id'   => $this->teacher->id,
            'title'        => 'Paid Course',
            'description'  => 'Description',
            'category_id'  => $this->category->id,
            'level_id'     => $this->level->id,
            'language'     => 'en',
            'price'        => $price,
            'platform_fee' => $price * 0.20,
            'status'       => 'published',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // COURSE PAYMENT INTENT
    // ═══════════════════════════════════════════════════════════════

    public function test_create_course_payment_intent(): void
    {
        $this->mockStripeService();
        $course = $this->createPublishedCourse();

        $response = $this->postJson('/api/v1/payments/course/intent', [
            'course_id' => $course->id,
        ], $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonStructure(['data' => [
                'client_secret',
                'payment_intent_id',
                'payment_id',
                'amount',
                'currency',
            ]]);

        $this->assertDatabaseHas('payments', [
            'user_id'      => $this->student->id,
            'payable_type' => 'course',
            'payable_id'   => $course->id,
            'status'       => 'pending',
        ]);
    }

    public function test_course_intent_fails_if_already_enrolled(): void
    {
        $this->mockStripeService();
        $course = $this->createPublishedCourse();
        CourseEnrollment::create([
            'course_id'  => $course->id,
            'student_id' => $this->student->id,
            'amount_paid' => $course->price,
        ]);

        $response = $this->postJson('/api/v1/payments/course/intent', [
            'course_id' => $course->id,
        ], $this->headers);

        $response->assertStatus(400)
            ->assertJson(['status' => false]);
    }

    public function test_free_course_enrolls_immediately(): void
    {
        $this->mockStripeService();
        $course = $this->createPublishedCourse(0);

        $response = $this->postJson('/api/v1/payments/course/intent', [
            'course_id' => $course->id,
        ], $this->headers);

        $response->assertStatus(201)
            ->assertJson(['status' => true]);

        $this->assertDatabaseHas('course_enrollments', [
            'course_id'  => $course->id,
            'student_id' => $this->student->id,
        ]);
    }

    public function test_course_intent_validation_fails(): void
    {
        $response = $this->postJson('/api/v1/payments/course/intent', [
            'course_id' => 99999,
        ], $this->headers);

        $response->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════════
    // CONFIRM COURSE PAYMENT
    // ═══════════════════════════════════════════════════════════════

    public function test_confirm_course_payment(): void
    {
        $this->mockStripeService();
        $course = $this->createPublishedCourse();

        $payment = Payment::create([
            'user_id'           => $this->student->id,
            'payable_type'      => 'course',
            'payable_id'        => $course->id,
            'amount'            => $course->price,
            'currency'          => 'USD',
            'status'            => 'pending',
            'payment_method'    => 'stripe',
            'payment_reference' => Payment::generateReference(),
            'transaction_id'    => 'pi_test_123',
        ]);

        $response = $this->postJson('/api/v1/payments/course/confirm', [
            'payment_intent_id' => 'pi_test_123',
            'course_id'         => $course->id,
        ], $this->headers);

        $response->assertStatus(201)
            ->assertJson(['status' => true]);
    }

    // ═══════════════════════════════════════════════════════════════
    // SESSION PAYMENT INTENT
    // ═══════════════════════════════════════════════════════════════

    public function test_create_session_payment_intent(): void
    {
        $this->mockStripeService();

        $session = MentorSession::create([
            'mentor_id'        => $this->mentor->id,
            'title'            => 'Paid Session',
            'type'             => 'individual',
            'start_time'       => now()->addDays(1),
            'end_time'         => now()->addDays(1)->addHour(),
            'max_seats'        => 1,
            'language'         => 'en',
            'price'            => 30.00,
            'duration_minutes' => 60,
            'status'           => 'upcoming',
            'meeting_room_id'  => 'torrino-test-' . uniqid(),
        ]);

        $response = $this->postJson('/api/v1/payments/session/intent', [
            'session_id' => $session->id,
        ], $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonStructure(['data' => ['client_secret', 'payment_intent_id']]);
    }

    // ═══════════════════════════════════════════════════════════════
    // SUBSCRIPTION PAYMENT INTENT
    // ═══════════════════════════════════════════════════════════════

    public function test_create_subscription_payment_intent(): void
    {
        $this->mockStripeService();

        $mentorHeaders = ['Authorization' => 'Bearer ' . $this->mentor->createToken('test')->plainTextToken];

        $response = $this->postJson('/api/v1/payments/subscription/intent', [
            'plan' => 'monthly',
            'role' => 'mentor',
        ], $mentorHeaders);

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonStructure(['data' => ['client_secret', 'payment_intent_id']]);
    }

    // ═══════════════════════════════════════════════════════════════
    // PAYMENT REQUIRES AUTH
    // ═══════════════════════════════════════════════════════════════

    public function test_payment_endpoints_require_auth(): void
    {
        $response = $this->postJson('/api/v1/payments/course/intent', [
            'course_id' => 1,
        ]);

        $response->assertStatus(401);
    }

    // ═══════════════════════════════════════════════════════════════
    // PAYMENT HISTORY
    // ═══════════════════════════════════════════════════════════════

    public function test_list_payment_history(): void
    {
        Payment::create([
            'user_id'           => $this->student->id,
            'payable_type'      => 'course',
            'payable_id'        => 1,
            'amount'            => 29.99,
            'currency'          => 'USD',
            'status'            => 'paid',
            'payment_method'    => 'stripe',
            'payment_reference' => Payment::generateReference(),
            'paid_at'           => now(),
        ]);

        $response = $this->getJson('/api/v1/payments', $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true]);
    }
}
