<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Balance ledger. Rows are immutable; users.balance_* are cached sums of these rows.
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['topup', 'stake_lock', 'stake_unlock', 'forfeit', 'payout', 'withdrawal', 'adjustment']);
            // Signed, kopecks.
            $table->bigInteger('amount');
            $table->enum('affects', ['available', 'locked']);
            // FK to challenges is added together with the challenges table.
            $table->unsignedBigInteger('challenge_id')->nullable()->index();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('withdrawal_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
