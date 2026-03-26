<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('mux_upload_id')->nullable()->after('video_url');
            $table->string('mux_asset_id')->nullable()->after('mux_upload_id');
            $table->string('mux_playback_id')->nullable()->after('mux_asset_id');
            $table->unsignedInteger('video_duration_seconds')->nullable()->after('mux_playback_id');
            $table->enum('video_status', ['pending', 'processing', 'ready', 'error'])
                ->nullable()
                ->after('video_duration_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn([
                'mux_upload_id',
                'mux_asset_id',
                'mux_playback_id',
                'video_duration_seconds',
                'video_status',
            ]);
        });
    }
};
