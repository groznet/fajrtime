<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin overrides for config/fajr.php 'defaults'. A missing key means "use the default".
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->string('value', 255);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
