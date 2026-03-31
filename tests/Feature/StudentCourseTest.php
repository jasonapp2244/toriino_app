<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseCertificate;
use App\Models\CourseEnrollment;
use App\Models\CourseFavorite;
use App\Models\CourseLevel;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentCourseTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $teacher;
    private CourseCategory $category;
    private CourseLevel $level;
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

        $this->teacher = User::factory()->create([
            'is_verified' => true,
            'status'      => 'active',
            'role'        => 'teacher',
        ]);
        $this->teacher->assignRole('teacher');

        $this->category = CourseCategory::create(['name' => 'Programming', 'slug' => 'programming', 'is_active' => true]);
        $this->level = CourseLevel::create(['name' => 'Beginner', 'slug' => 'beginner', 'is_active' => true]);
    }

    private function createPublishedCourse(array $overrides = []): Course
    {
        return Course::create(array_merge([
            'teacher_id'  => $this->teacher->id,
            'title'       => 'Laravel Course',
            'description' => 'Learn Laravel',
            'category_id' => $this->category->id,
            'level_id'    => $this->level->id,
            'language'    => 'en',
            'price'       => 29.99,
            'status'      => 'published',
        ], $overrides));
    }

    // ═══════════════════════════════════════════════════════════════
    // BROWSE COURSES (PUBLIC)
    // ═══════════════════════════════════════════════════════════════

    public function test_browse_courses_returns_published_courses(): void
    {
        $this->createPublishedCourse();
        Course::create([
            'teacher_id'  => $this->teacher->id,
            'title'       => 'Draft Course',
            'description' => 'Not published',
            'category_id' => $this->category->id,
            'level_id'    => $this->level->id,
            'language'    => 'en',
            'price'       => 10,
            'status'      => 'draft',
        ]);

        $response = $this->getJson('/api/v1/courses');

        $response->assertStatus(200)
            ->assertJson(['status' => true]);

        // Only published courses should appear
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('Laravel Course', $data[0]['title']);
    }

    public function test_browse_courses_with_filters(): void
    {
        $this->createPublishedCourse(['title' => 'PHP Basics', 'language' => 'en']);
        $this->createPublishedCourse(['title' => 'Vue.js', 'language' => 'fr']);

        $response = $this->getJson('/api/v1/courses?language=en');

        $response->assertStatus(200);
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('PHP Basics', $data[0]['title']);
    }

    public function test_show_single_course(): void
    {
        $course = $this->createPublishedCourse();

        $response = $this->getJson("/api/v1/courses/{$course->id}");

        $response->assertStatus(200)
            ->assertJson(['status' => true])
            ->assertJsonPath('data.title', 'Laravel Course');
    }

    public function test_show_course_not_found(): void
    {
        $response = $this->getJson('/api/v1/courses/99999');

        $response->assertStatus(404)
            ->assertJson(['status' => false]);
    }

    // ═══════════════════════════════════════════════════════════════
    // ENROLL
    // ═══════════════════════════════════════════════════════════════

    public function test_enroll_in_course_success(): void
    {
        $course = $this->createPublishedCourse();

        $response = $this->postJson('/api/v1/student/courses/enroll', [
            'course_id' => $course->id,
        ], $this->headers);

        $response->assertStatus(201)
            ->assertJson(['status' => true, 'message' => 'Enrolled successfully']);

        $this->assertDatabaseHas('course_enrollments', [
            'course_id'  => $course->id,
            'student_id' => $this->student->id,
        ]);
    }

    public function test_enroll_fails_if_already_enrolled(): void
    {
        $course = $this->createPublishedCourse();
        CourseEnrollment::create([
            'course_id'  => $course->id,
            'student_id' => $this->student->id,
            'amount_paid' => $course->price,
        ]);

        $response = $this->postJson('/api/v1/student/courses/enroll', [
            'course_id' => $course->id,
        ], $this->headers);

        $response->assertStatus(400)
            ->assertJson(['status' => false]);
    }

    public function test_enroll_fails_without_auth(): void
    {
        $course = $this->createPublishedCourse();

        $response = $this->postJson('/api/v1/student/courses/enroll', [
            'course_id' => $course->id,
        ]);

        $response->assertStatus(401);
    }

    public function test_enroll_validation_fails_with_invalid_course(): void
    {
        $response = $this->postJson('/api/v1/student/courses/enroll', [
            'course_id' => 99999,
        ], $this->headers);

        $response->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════════
    // MY COURSES
    // ═══════════════════════════════════════════════════════════════

    public function test_my_courses_returns_enrolled_courses(): void
    {
        $course = $this->createPublishedCourse();
        CourseEnrollment::create([
            'course_id'  => $course->id,
            'student_id' => $this->student->id,
            'amount_paid' => $course->price,
            'status'     => 'active',
        ]);

        $response = $this->getJson('/api/v1/student/courses/my', $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true]);
    }

    public function test_my_courses_empty_when_not_enrolled(): void
    {
        $response = $this->getJson('/api/v1/student/courses/my', $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true]);
    }

    // ═══════════════════════════════════════════════════════════════
    // COURSE PROGRESS
    // ═══════════════════════════════════════════════════════════════

    public function test_course_progress_returns_data(): void
    {
        $course = $this->createPublishedCourse();
        CourseEnrollment::create([
            'course_id'       => $course->id,
            'student_id'      => $this->student->id,
            'amount_paid'     => $course->price,
            'progress_percent' => 50,
            'status'          => 'active',
        ]);

        $response = $this->getJson("/api/v1/student/courses/{$course->id}/progress", $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true]);
    }

    // ═══════════════════════════════════════════════════════════════
    // FAVORITES
    // ═══════════════════════════════════════════════════════════════

    public function test_toggle_favorite_course(): void
    {
        $course = $this->createPublishedCourse();

        // Add to favorites
        $response = $this->postJson("/api/v1/student/courses/{$course->id}/favorite", [], $this->headers);
        $response->assertStatus(200)->assertJson(['status' => true]);

        $this->assertDatabaseHas('course_favorites', [
            'student_id' => $this->student->id,
            'course_id'  => $course->id,
        ]);

        // Toggle off (remove from favorites)
        $response = $this->postJson("/api/v1/student/courses/{$course->id}/favorite", [], $this->headers);
        $response->assertStatus(200)->assertJson(['status' => true]);

        $this->assertDatabaseMissing('course_favorites', [
            'student_id' => $this->student->id,
            'course_id'  => $course->id,
        ]);
    }

    public function test_list_favorites(): void
    {
        $course = $this->createPublishedCourse();
        CourseFavorite::create([
            'student_id' => $this->student->id,
            'course_id'  => $course->id,
        ]);

        $response = $this->getJson('/api/v1/student/courses/favorites', $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true]);
    }

    // ═══════════════════════════════════════════════════════════════
    // LESSON DETAIL
    // ═══════════════════════════════════════════════════════════════

    public function test_lesson_detail_for_enrolled_student(): void
    {
        $course = $this->createPublishedCourse();
        $lesson = Lesson::create([
            'course_id'   => $course->id,
            'title'       => 'Intro Lesson',
            'description' => 'Welcome',
            'order'       => 1,
        ]);
        CourseEnrollment::create([
            'course_id'  => $course->id,
            'student_id' => $this->student->id,
            'amount_paid' => $course->price,
            'status'     => 'active',
        ]);

        $response = $this->getJson(
            "/api/v1/student/courses/{$course->id}/lessons/{$lesson->id}",
            $this->headers
        );

        $response->assertStatus(200)
            ->assertJson(['status' => true]);
    }

    // ═══════════════════════════════════════════════════════════════
    // COMPLETE LESSON
    // ═══════════════════════════════════════════════════════════════

    public function test_complete_lesson(): void
    {
        $course = $this->createPublishedCourse();
        $lesson = Lesson::create([
            'course_id'   => $course->id,
            'title'       => 'Lesson 1',
            'description' => 'First lesson',
            'order'       => 1,
        ]);
        CourseEnrollment::create([
            'course_id'  => $course->id,
            'student_id' => $this->student->id,
            'amount_paid' => $course->price,
            'status'     => 'active',
        ]);

        // Set video progress to 95% so the lesson can be completed
        $enrollment = CourseEnrollment::where('course_id', $course->id)
            ->where('student_id', $this->student->id)->first();
        \App\Models\LessonVideoProgress::create([
            'enrollment_id'      => $enrollment->id,
            'lesson_id'          => $lesson->id,
            'student_id'         => $this->student->id,
            'percentage_watched' => 95,
            'last_position'      => 570,
        ]);

        $response = $this->postJson(
            "/api/v1/student/courses/{$course->id}/lessons/{$lesson->id}/complete",
            [],
            $this->headers
        );

        $response->assertStatus(200)
            ->assertJson(['status' => true]);
    }

    // ═══════════════════════════════════════════════════════════════
    // CERTIFICATE
    // ═══════════════════════════════════════════════════════════════

    public function test_get_certificate_for_completed_course(): void
    {
        $course = $this->createPublishedCourse();
        $enrollment = CourseEnrollment::create([
            'course_id'       => $course->id,
            'student_id'      => $this->student->id,
            'amount_paid'     => $course->price,
            'status'          => 'completed',
            'progress_percent' => 100,
            'completed_at'    => now(),
        ]);
        CourseCertificate::create([
            'enrollment_id'     => $enrollment->id,
            'student_id'        => $this->student->id,
            'course_id'         => $course->id,
            'certificate_number' => 'CERT-000001',
            'issued_at'         => now(),
        ]);

        $response = $this->getJson(
            "/api/v1/student/courses/{$course->id}/certificate",
            $this->headers
        );

        $response->assertStatus(200)
            ->assertJson(['status' => true]);
    }

    // ═══════════════════════════════════════════════════════════════
    // ROLE ENFORCEMENT
    // ═══════════════════════════════════════════════════════════════

    public function test_mentor_cannot_access_student_course_routes(): void
    {
        $mentor = User::factory()->create([
            'is_verified' => true,
            'status'      => 'active',
            'role'        => 'mentor',
        ]);
        $mentor->assignRole('mentor');
        $token = $mentor->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/v1/student/courses/my', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(403);
    }
}
