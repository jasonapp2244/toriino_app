<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!$this->indexExists('session_bookings', 'session_bookings_unique')) {
            Schema::table('session_bookings', function (Blueprint $table) {
                $table->unique(['session_id', 'student_id'], 'session_bookings_unique');
            });
        }

        // lesson_video_progress already has unique(['enrollment_id', 'lesson_id']) from creation migration
        // course_favorites already has unique(['student_id', 'course_id']) from creation migration

        // Index for faster earning lookups
        if (!$this->indexExists('earnings', 'earnings_reference_index')) {
            Schema::table('earnings', function (Blueprint $table) {
                $table->index(['reference_id', 'reference_type'], 'earnings_reference_index');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }

    public function down(): void
    {
        Schema::table('session_bookings', function (Blueprint $table) {
            $table->dropUnique('session_bookings_unique');
        });

        Schema::table('earnings', function (Blueprint $table) {
            $table->dropIndex('earnings_reference_index');
        });
    }
};
