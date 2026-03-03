<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Student Profile ─────────────────────────────────────
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->string('image')->nullable()->after('student_id');         // profile photo
            $table->text('bio')->nullable()->after('image');                  // short bio
            $table->string('language')->default('English')->after('bio');     // preferred language
        });

        // ─── Mentor Profile ──────────────────────────────────────
        Schema::table('mentor_profiles', function (Blueprint $table) {
            $table->string('designation')->nullable()->after('user_id');      // e.g. "Senior Developer"
            $table->text('short_bio')->nullable()->after('designation');      // short bio (separate from intro)
            $table->string('industry')->nullable()->after('short_bio');       // e.g. "Technology", "Finance"
            $table->json('expertise_list')->nullable()->after('industry');    // ["Laravel","Vue","AWS"]
            $table->string('preferred_student_level')                         // beginner|intermediate|advanced|all
                ->default('all')->after('expertise_list');
            $table->json('languages_list')->nullable()->after('languages');   // ["English","Arabic","French"]
        });

        // ─── Teacher Profile ─────────────────────────────────────
        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->string('subject_field')->nullable()->after('subject');    // detailed subject field
            $table->text('short_bio')->nullable()->after('subject_field');    // short bio
            $table->json('expertise_list')->nullable()->after('short_bio');   // ["Algebra","Calculus"]
            $table->json('languages_list')->nullable()->after('languages');   // ["English","Urdu"]
            $table->string('designation')->nullable()->after('degree');       // e.g. "Associate Professor"
        });
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropColumn(['image', 'bio', 'language']);
        });

        Schema::table('mentor_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'designation', 'short_bio', 'industry',
                'expertise_list', 'preferred_student_level', 'languages_list',
            ]);
        });

        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'subject_field', 'short_bio', 'expertise_list',
                'languages_list', 'designation',
            ]);
        });
    }
};
