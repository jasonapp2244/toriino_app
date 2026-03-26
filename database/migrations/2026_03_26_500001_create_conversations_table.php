<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();

            // Participant IDs — role-agnostic: any user type can talk to any other
            $table->foreignId('participant_one_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->foreignId('participant_two_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            // Ensure only one conversation exists per participant pair (in either order)
            $table->unique(['participant_one_id', 'participant_two_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
