<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Confirmations pressed in the Telegram app, waiting for the browser that asked (App\Support\TelegramAppLogin)
        Schema::create('telegram_app_logins', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->json('data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_app_logins');
    }
};
