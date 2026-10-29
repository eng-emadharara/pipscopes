<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Member notifications by email and Telegram: each member's choices, and a log of every message sent. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_prefs')->nullable();         // type => {mail: bool, telegram: bool} (App\Support\NotificationPrefs)
            $table->timestamp('telegram_blocked_at')->nullable();   // the member blocked our bot, so Telegram messages are skipped
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40)->index();       // review_status, owner_reply…
            $table->string('channel', 20);             // mail | telegram
            $table->string('status', 20)->index();     // sent | failed
            $table->string('subject')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['notification_prefs', 'telegram_blocked_at']));
    }
};
