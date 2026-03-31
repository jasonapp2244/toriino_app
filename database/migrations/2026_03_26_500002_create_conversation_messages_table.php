<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('conversation_messages')) return;
        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')
                  ->constrained('conversations')
                  ->cascadeOnDelete();

            $table->foreignId('sender_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->text('message');

            // Message type: text | image | file
            $table->string('type', 20)->default('text');

            // Optional file attachment URL (for images/files sent in chat)
            $table->string('attachment_url')->nullable();

            $table->boolean('is_read')->default(false);

            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['sender_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
    }
};
