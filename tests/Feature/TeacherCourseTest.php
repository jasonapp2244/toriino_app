<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseLevel;
use App\Models\Lesson;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeacherCourseTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private CourseCategory $category;
    private CourseLevel $level;
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->teacher = User::factory()->create([
            'is_verified' => true,
            'status'      => 'active',
            'role'        => 'teacher',
        ]);
        $this->teacher->assignRole('teacher');
        $this->headers = ['Authorization' => 'Bearer ' . $this->teacher->createToken('test')->plainTextToken];

        $this->category = CourseCategory::create(['name' => 'Web Dev', 'slug' => 'web-dev', 'is_active' => true]);
        $this->level = CourseLevel::create(['name' => 'Beginner', 'slug' => 'beginner', 'is_active' => true]);
    }

    private function giveSubscription(): void
    {
        Subscription::create([
            'user_id'    => $this->teacher->id,
            'plan'       => 'monthly',
            'price'      => 9.99,
            'starts_at'  => now(),
            'expires_at' => now()->addDays(30),
            'status'     => 'active',
        ]);
    }

    private function createCourse(array $overrides = []): Course
    {
        return Course::create(array_merge([
            'teacher_id'  => $this->teacher->id,
            'title'       => 'Test Course',
            'description' => 'Description here',
            'category_id' => $this->category->id,
            'level_id'    => $this->level->id,
            'language'    => 'en',
            'price'       => 29.99,
            'status'      => 'draft',
        ], $overrides));
    }

    // ═══════════════════════════════════════════════════════════════
    // CREATE COURSE
    // ═══════════════════════════════════════════════════════════════

    public function test_create_course_as_draft(): void
    {
        $response = $this->postJson('/api/v1/teacher/courses', [
            'title'       => 'New Course',
            'description' => 'Course description',
            'category_id' => $this->category->id,
            'level_id'    => $this->level->id,
            'language'    => 'en',
            'price'       => 49.99,
        ], $this->headers);

        $response->assertStatus(201)
            ->assertJson(['status' => true, 'message' => 'Course created']);

        $this->assertDatabaseHas('courses', [
            'teacher_id' => $this->teacher->id,
            'title'      => 'New Course',
            'status'     => 'draft',
        ]);
    }

    public function test_create_course_validation_fails(): void
    {
        $response = $this->postJson('/api/v1/teacher/courses', [], $this->headers);

        $response->assertStatus(422);
    }

    public function test_create_course_does_not_require_subscription(): void
    {
        // Creating a draft is free even without subscription
        $response = $this->postJson('/api/v1/teacher/courses', [
            'title'       => 'Free Draft',
            'description' => 'Draft course',
            'category_id' => $this->category->id,
            'level_id'    => $this->level->id,
            'language'    => 'en',
            'price'       => 0,
        ], $this->headers);

        $response->assertStatus(201);
    }

    // ═══════════════════════════════════════════════════════════════
    // UPDATE COURSE
    // ═══════════════════════════════════════════════════════════════

    public function test_update_course_success(): void
    {
        $course = $this->createCourse();

        $response = $this->putJson("/api/v1/teacher/courses/{$course->id}", [
            'title' => 'Updated Title',
            'price' => 39.99,
        ], $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true, 'message' => 'Course updated']);

        $this->assertDatabaseHas('courses', [
            'id'    => $course->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_update_other_teachers_course_returns_404(): void
    {
        $otherTeacher = User::factory()->create([
            'is_verified' => true, 'status' => 'active', 'role' => 'teacher',
        ]);
        $otherTeacher->assignRole('teacher');

        $course = Course::create([
            'teacher_id'  => $otherTeacher->id,
            'title'       => 'Other Course',
            'description' => 'Desc',
            'category_id' => $this->category->id,
            'level_id'    => $this->level->id,
            'language'    => 'en',
            'price'       => 10,
            'status'      => 'draft',
        ]);

        $response = $this->putJson("/api/v1/teacher/courses/{$course->id}", [
            'title' => 'Hijacked',
        ], $this->headers);

        $response->assertStatus(404);
    }

    // ═══════════════════════════════════════════════════════════════
    // PUBLISH COURSE (needs subscription + min lessons)
    // ═══════════════════════════════════════════════════════════════

    public function test_publish_course_fails_without_subscription(): void
    {
        $course = $this->createCourse();

        $response = $this->postJson("/api/v1/teacher/courses/{$course->id}/publish", [], $this->headers);

        $response->assertStatus(402)
            ->assertJson(['status' => false])
            ->assertJsonPath('errors.subscription_required', true);
    }

    public function test_publish_course_fails_without_enough_lessons(): void
    {
        $this->giveSubscription();
        $course = $this->createCourse();

        // Only 2 lessons (need at least 5)
        for ($i = 1; $i <= 2; $i++) {
            Lesson::create([
                'course_id' => $course->id,
                'title'     => "Lesson $i",
                'order'     => $i,
                'video_url' => 'https://example.com/video.mp4',
            ]);
        }

        $response = $this->postJson("/api/v1/teacher/courses/{$course->id}/publish", [], $this->headers);

        $response->assertStatus(400)
            ->assertJson(['status' => false]);
    }

    public function test_publish_course_fails_without_videos(): void
    {
        $this->giveSubscription();
        $course = $this->createCourse();

        // 5 lessons but no videos
        for ($i = 1; $i <= 5; $i++) {
            Lesson::create([
                'course_id' => $course->id,
                'title'     => "Lesson $i",
                'order'     => $i,
            ]);
        }

        $response = $this->postJson("/api/v1/teacher/courses/{$course->id}/publish", [], $this->headers);

        $response->assertStatus(400)
            ->assertJson(['status' => false]);
    }

    public function test_publish_course_success(): void
    {
        $this->giveSubscription();
        $course = $this->createCourse();

        // 5 lessons with videos
        for ($i = 1; $i <= 5; $i++) {
            Lesson::create([
                'course_id' => $course->id,
                'title'     => "Lesson $i",
                'order'     => $i,
                'video_url' => "https://example.com/video{$i}.mp4",
            ]);
        }

        $response = $this->postJson("/api/v1/teacher/courses/{$course->id}/publish", [], $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true, 'message' => 'Course published successfully.']);

        $this->assertDatabaseHas('courses', [
            'id'     => $course->id,
            'status' => 'published',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // ADD LESSON
    // ═══════════════════════════════════════════════════════════════

    public function test_add_lesson_to_course(): void
    {
        $course = $this->createCourse();

        $response = $this->postJson("/api/v1/teacher/courses/{$course->id}/lessons", [
            'title'       => 'New Lesson',
            'description' => 'Lesson description',
            'order'       => 1,
        ], $this->headers);

        $response->assertStatus(201)
            ->assertJson(['status' => true]);

        $this->assertDatabaseHas('lessons', [
            'course_id' => $course->id,
            'title'     => 'New Lesson',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // DELETE LESSON
    // ═══════════════════════════════════════════════════════════════

    public function test_delete_lesson(): void
    {
        $course = $this->createCourse();
        $lesson = Lesson::create([
            'course_id' => $course->id,
            'title'     => 'Deletable Lesson',
            'order'     => 1,
        ]);

        $response = $this->deleteJson("/api/v1/teacher/lessons/{$lesson->id}", [], $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true]);
    }

    // ═══════════════════════════════════════════════════════════════
    // DELETE COURSE
    // ═══════════════════════════════════════════════════════════════

    public function test_delete_course_archives_it(): void
    {
        $course = $this->createCourse();

        $response = $this->deleteJson("/api/v1/teacher/courses/{$course->id}", [], $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true, 'message' => 'Course archived']);

        $this->assertDatabaseHas('courses', [
            'id'     => $course->id,
            'status' => 'archived',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // LIST COURSES
    // ═══════════════════════════════════════════════════════════════

    public function test_list_teacher_courses(): void
    {
        $this->createCourse(['title' => 'Course 1']);
        $this->createCourse(['title' => 'Course 2']);

        $response = $this->getJson('/api/v1/teacher/courses', $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true]);

        $this->assertCount(2, $response->json('data.data'));
    }

    // ═══════════════════════════════════════════════════════════════
    // UPLOAD THUMBNAIL
    // ═══════════════════════════════════════════════════════════════

    public function test_upload_thumbnail(): void
    {
        Storage::fake('public');
        $course = $this->createCourse();

        $response = $this->postJson("/api/v1/teacher/courses/{$course->id}/thumbnail", [
            'thumbnail' => UploadedFile::fake()->create('thumb.jpg', 100, 'image/jpeg'),
        ], $this->headers);

        $response->assertStatus(200)
            ->assertJson(['status' => true]);
    }

    // ═══════════════════════════════════════════════════════════════
    // ROLE ENFORCEMENT
    // ═══════════════════════════════════════════════════════════════

    public function test_student_cannot_access_teacher_course_routes(): void
    {
        $student = User::factory()->create([
            'is_verified' => true, 'status' => 'active', 'role' => 'student',
        ]);
        $student->assignRole('student');
        $token = $student->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/v1/teacher/courses', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(403);
    }
}
