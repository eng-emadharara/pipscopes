<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Announcements the admin sends to members by email and Telegram, and per-message stats. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('body');
            $table->string('button_label', 40)->nullable();
            $table->json('translations')->nullable();          // title/body/button_label in other languages
            $table->string('button_url')->nullable();
            $table->string('image')->nullable();               // public disk, announcements/
            $table->json('audience')->nullable();              // App\Support\AnnouncementAudience
            $table->string('status', 20)->default('draft')->index(); // draft | scheduled | sending | sent
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('clicks_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('notification_logs', function (Blueprint $table) {
            $table->foreignId('announcement_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        // The language the member last used on the site, for their emails and Telegram messages
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', fn (Blueprint $table) => $table->dropConstrainedForeignId('announcement_id'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('locale'));
        Schema::dropIfExists('announcements');
    }
};
