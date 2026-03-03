<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('language')->default('English');
            $table->string('duration')->nullable();
            $table->decimal('price', 8, 2)->default(0);
            $table->decimal('platform_fee', 8, 2)->default(0);
            $table->string('thumbnail')->nullable();
            $table->string('intro_video')->nullable();
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('total_enrollments')->default(0);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
