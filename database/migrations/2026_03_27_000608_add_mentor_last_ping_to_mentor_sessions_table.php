<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentor_sessions', function (Blueprint $table) {
            // Updated every 30s by mentor app while in call. Used to detect orphaned sessions.
            $table->timestamp('mentor_last_ping_at')->nullable()->after('meeting_room_id');
        });
    }

    public function down(): void
    {
        Schema::table('mentor_sessions', function (Blueprint $table) {
            $table->dropColumn('mentor_last_ping_at');
        });
    }
};
