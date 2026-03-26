<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('session_messages');
    }

    public function down(): void
    {
        // Restore via the original migration if needed
    }
};
