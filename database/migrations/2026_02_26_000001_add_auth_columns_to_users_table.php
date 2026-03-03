<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // rename 'name' → keep for Spatie/Laravel compat, add full_name
            $table->string('full_name')->nullable()->after('id');
            $table->string('phone', 20)->nullable()->after('email');
            $table->enum('role', ['student', 'mentor', 'teacher'])->nullable()->after('phone');
            $table->string('profile')->nullable()->after('role');         // photo URL
            $table->string('otp_code', 6)->nullable()->after('password');
            $table->timestamp('otp_expires_at')->nullable()->after('otp_code');
            $table->boolean('is_verified')->default(false)->after('otp_expires_at');
            $table->enum('status', ['active', 'inactive', 'banned'])->default('active')->after('is_verified');
            $table->boolean('two_factor_enabled')->default(false)->after('status');
            $table->string('provider')->nullable()->after('two_factor_enabled');     // google | apple
            $table->string('provider_id')->nullable()->after('provider');
            $table->string('timezone')->default('UTC')->after('provider_id');
            $table->string('language', 10)->default('en')->after('timezone');
            $table->string('fcm_token')->nullable()->after('language');
            $table->string('device_id')->nullable()->after('fcm_token');
            $table->string('device_type', 10)->nullable()->after('device_id');       // ios | android
            $table->timestamp('last_active_at')->nullable()->after('device_type');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'full_name', 'phone', 'role', 'profile',
                'otp_code', 'otp_expires_at', 'is_verified', 'status',
                'two_factor_enabled', 'provider', 'provider_id',
                'timezone', 'language', 'fcm_token', 'device_id',
                'device_type', 'last_active_at', 'deleted_at',
            ]);
        });
    }
};
