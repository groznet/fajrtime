<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->restrictOnDelete();
            // Copied from the group at creation: the rules of a challenge are frozen.
            $table->bigInteger('stake_amount');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['recruiting', 'active', 'settling', 'completed', 'cancelled'])->default('recruiting');
            // Filled at settlement, kopecks.
            $table->bigInteger('forfeit_pool')->nullable();
            $table->bigInteger('survivor_share')->nullable();
            $table->timestamps();

            $table->index(['status', 'start_date']);
            $table->index(['group_id', 'status']);
        });

        Schema::create('challenge_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['active', 'eliminated', 'survived', 'left'])->default('active');
            $table->date('eliminated_on')->nullable();
            // Stake + share of the forfeit pool, set at settlement.
            $table->bigInteger('payout_amount')->nullable();
            $table->timestamps();

            $table->unique(['challenge_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('challenge_id')->references('id')->on('challenges')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['challenge_id']);
        });
        Schema::dropIfExists('challenge_participants');
        Schema::dropIfExists('challenges');
    }
};
