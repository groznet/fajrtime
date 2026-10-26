<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Manual withdrawal requests (processed by an admin in MVP). Amount in kopecks.
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount');
            $table->enum('status', ['requested', 'approved', 'paid', 'rejected'])->default('requested');
            $table->json('details');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
