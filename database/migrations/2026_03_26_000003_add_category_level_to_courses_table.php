<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            // Drop the old free-text category column
            $table->dropColumn('category');

            // Add FK references to the new lookup tables
            $table->foreignId('category_id')
                ->nullable()
                ->after('description')
                ->constrained('course_categories')
                ->nullOnDelete();

            $table->foreignId('level_id')
                ->nullable()
                ->after('category_id')
                ->constrained('course_levels')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['level_id']);
            $table->dropColumn(['category_id', 'level_id']);
            $table->string('category')->nullable()->after('description');
        });
    }
};
