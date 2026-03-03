<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('payable_type', ['session', 'course']);
            $table->unsignedBigInteger('payable_id');   // session_id or course_id
            $table->decimal('amount', 10, 2);
            $table->string('currency', 5)->default('USD');
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->string('payment_method')->nullable();          // card | wallet | stripe | paypal
            $table->string('transaction_id')->nullable()->unique(); // from payment gateway
            $table->string('payment_reference')->unique();         // internal ref
            $table->json('gateway_response')->nullable();          // raw gateway payload
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
