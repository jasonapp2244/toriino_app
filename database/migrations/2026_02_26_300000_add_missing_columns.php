<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add lesson progress tracking + completed_at to course_enrollments
        // (courses.thumbnail already exists from the original migration)
        if (!Schema::hasColumn('course_enrollments', 'progress')) {
            Schema::table('course_enrollments', function (Blueprint $table) {
                $table->json('progress')->nullable()->after('progress_percent');
                $table->timestamp('completed_at')->nullable()->after('progress');
            });
        }
    }

    public function down(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropColumn(['progress', 'completed_at']);
        });
    }
};
