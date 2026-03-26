<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('availabilities', function (Blueprint $table) {
            $table->unsignedTinyInteger('min_students')->default(1)->after('is_available');
            $table->unsignedTinyInteger('max_students')->default(5)->after('min_students');
        });
    }

    public function down(): void
    {
        Schema::table('availabilities', function (Blueprint $table) {
            $table->dropColumn(['min_students', 'max_students']);
        });
    }
};
