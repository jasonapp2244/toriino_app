<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentor_sessions', function (Blueprint $table) {
            // Join key for group sessions — shared manually by mentor so students can join
            $table->string('join_key', 10)->nullable()->unique()->after('status');
            // Enforce max 5 students for group sessions at DB level via default; controller enforces the rule
            $table->unsignedTinyInteger('group_max_seats')->default(5)->after('join_key');
        });
    }

    public function down(): void
    {
        Schema::table('mentor_sessions', function (Blueprint $table) {
            $table->dropColumn(['join_key', 'group_max_seats']);
        });
    }
};
