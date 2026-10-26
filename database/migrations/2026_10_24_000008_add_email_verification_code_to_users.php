<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The 6-digit code is stored hashed, like a password
        Schema::table('users', function (Blueprint $table) {
            $table->string('verification_code')->nullable()->after('email_verified_at');
            $table->timestamp('verification_code_expires_at')->nullable()->after('verification_code');
            $table->timestamp('verification_code_sent_at')->nullable()->after('verification_code_expires_at');
            $table->unsignedTinyInteger('verification_attempts')->default(0)->after('verification_code_sent_at');
        });

        // Accounts created before verification existed (including admins) are trusted as-is
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'verification_code', 'verification_code_expires_at', 'verification_code_sent_at', 'verification_attempts',
        ]));
    }
};
