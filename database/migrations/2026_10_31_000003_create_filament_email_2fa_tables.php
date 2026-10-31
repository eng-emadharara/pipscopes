<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Email codes for signing in to the admin panel (solution-forest/filament-email-2fa) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('filament-email-2fa.code_table'), function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->morphs('user');
            // An HMAC of the code, never the code itself (App\Models\User::generate2FACode)
            $table->string('code', 64);
            $table->dateTime('expiry_at');
        });

        // One row per session that entered a correct code
        Schema::create(config('filament-email-2fa.verify_table'), function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->morphs('user');
            $table->string('session_id')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('filament-email-2fa.verify_table'));
        Schema::dropIfExists(config('filament-email-2fa.code_table'));
    }
};
