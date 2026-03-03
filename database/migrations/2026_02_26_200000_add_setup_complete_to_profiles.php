<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Student profile setup flag
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->boolean('profile_setup_complete')->default(false)->after('certificates_earned');
        });

        // Mentor profile setup flag + pending switch tracker
        Schema::table('mentor_profiles', function (Blueprint $table) {
            $table->boolean('profile_setup_complete')->default(false)->after('is_featured');
        });

        // Teacher profile setup flag + pending switch tracker
        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->boolean('profile_setup_complete')->default(false)->after('is_verified');
        });

        // Track pending role switch on users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('pending_role')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('student_profiles',  fn($t) => $t->dropColumn('profile_setup_complete'));
        Schema::table('mentor_profiles',   fn($t) => $t->dropColumn('profile_setup_complete'));
        Schema::table('teacher_profiles',  fn($t) => $t->dropColumn('profile_setup_complete'));
        Schema::table('users',             fn($t) => $t->dropColumn('pending_role'));
    }
};
