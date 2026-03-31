<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentor_sessions', function (Blueprint $table) {
            $table->string('meeting_room_id')->nullable()->after('join_key');
        });
    }

    public function down(): void
    {
        Schema::table('mentor_sessions', function (Blueprint $table) {
            $table->dropColumn('meeting_room_id');
        });
    }
};
