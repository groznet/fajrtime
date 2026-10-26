<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per user per day; it covers all of the user's active challenges.
        Schema::create('check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            // Local date of the Fajr.
            $table->date('date');
            $table->timestamp('fajr_at');
            // Generated in advance, but returned to the browser only after reveal_at.
            $table->char('code', 6);
            $table->timestamp('reveal_at');
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->enum('status', ['pending', 'confirmed', 'missed'])->default('pending');
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_ins');
    }
};
