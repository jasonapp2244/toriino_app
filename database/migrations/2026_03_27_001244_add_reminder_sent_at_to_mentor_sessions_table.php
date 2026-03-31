<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentor_sessions', function (Blueprint $table) {
            // Timestamp set when the 5-min reminder has been sent — prevents duplicate reminders.
            $table->timestamp('reminder_sent_at')->nullable()->after('mentor_last_ping_at');
        });
    }

    public function down(): void
    {
        Schema::table('mentor_sessions', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
