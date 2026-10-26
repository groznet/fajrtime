<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Proof of acceptance of the terms and, separately, of consent to personal data processing
        // (152-FZ art. 9: the operator must be able to prove consent, and it is given separately).
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable()->after('email_verified_at');
            $table->timestamp('personal_data_consent_at')->nullable()->after('terms_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['terms_accepted_at', 'personal_data_consent_at']);
        });
    }
};
