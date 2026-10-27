<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The add-channel form now only asks for the Telegram link, markets and categories: everything
 * else is read from the channel's public page, and the sender is no longer asked who they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_submissions', function (Blueprint $table) {
            $table->string('submitter_name')->nullable()->change();
            $table->string('submitter_contact')->nullable()->change();
            $table->string('relation')->nullable()->default(null)->change();
            // A stored path on the public disk, or Telegram's own URL when the download failed
            $table->string('avatar')->nullable()->after('description');
            $table->json('telegram_data')->nullable()->after('avatar');
        });

        Schema::table('providers', function (Blueprint $table) {
            $table->json('telegram_data')->nullable()->after('telegram_url');
        });
    }

    public function down(): void
    {
        Schema::table('providers', fn (Blueprint $table) => $table->dropColumn('telegram_data'));
        Schema::table('provider_submissions', fn (Blueprint $table) => $table->dropColumn(['avatar', 'telegram_data']));
    }
};
