<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->decimal('latitude', 9, 6);
            $table->decimal('longitude', 9, 6);
            $table->string('timezone', 64);
            // Key in config/prayer.php 'methods' (fajr/isha angles).
            $table->string('calc_method', 50)->default('russia');
            // Asr shadow factor: shafii = 1, hanafi = 2.
            $table->enum('asr_method', ['shafii', 'hanafi'])->default('shafii');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
