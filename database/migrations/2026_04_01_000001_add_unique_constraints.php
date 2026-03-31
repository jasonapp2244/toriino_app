<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_bookings', function (Blueprint $table) {
            $table->unique(['session_id', 'student_id'], 'session_bookings_unique');
        });

        Schema::table('lesson_video_progress', function (Blueprint $table) {
            $table->unique(['lesson_id', 'student_id'], 'lesson_video_progress_unique');
        });

        Schema::table('course_favorites', function (Blueprint $table) {
            $table->unique(['course_id', 'student_id'], 'course_favorites_unique');
        });

        // Index for faster earning lookups
        Schema::table('earnings', function (Blueprint $table) {
            $table->index(['reference_id', 'reference_type'], 'earnings_reference_index');
        });
    }

    public function down(): void
    {
        Schema::table('session_bookings', function (Blueprint $table) {
            $table->dropUnique('session_bookings_unique');
        });

        Schema::table('lesson_video_progress', function (Blueprint $table) {
            $table->dropUnique('lesson_video_progress_unique');
        });

        Schema::table('course_favorites', function (Blueprint $table) {
            $table->dropUnique('course_favorites_unique');
        });

        Schema::table('earnings', function (Blueprint $table) {
            $table->dropIndex('earnings_reference_index');
        });
    }
};
