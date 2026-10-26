<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Top-ups. Amounts in kopecks.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('gateway', 30);
            // Null until the gateway has accepted the payment.
            $table->string('gateway_payment_id', 100)->nullable()->unique();
            $table->bigInteger('amount_gross');
            $table->bigInteger('commission');
            $table->bigInteger('amount_net');
            $table->enum('status', ['pending', 'succeeded', 'failed', 'refunded'])->default('pending');
            $table->string('confirmation_url', 500)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
