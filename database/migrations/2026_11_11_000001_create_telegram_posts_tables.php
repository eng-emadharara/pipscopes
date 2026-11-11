<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a channel's posts do, from its public preview page (App\Services\TelegramPosts):
 * each post seen in the last 45 days, and a small daily summary kept for good.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Numbers only: no post text or media is stored
        Schema::create('telegram_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('msg_id');
            $table->timestamp('posted_at')->nullable();
            $table->unsignedInteger('views')->nullable();
            $table->boolean('edited')->default(false);
            $table->timestamp('first_seen_at');
            // Seen, then gone from the page while the posts around it are still there
            $table->timestamp('deleted_at')->nullable();
            $table->unique(['provider_id', 'msg_id']);
            $table->index(['provider_id', 'posted_at']);
        });

        Schema::create('telegram_post_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedSmallInteger('posts')->default(0);
            $table->unsignedSmallInteger('deleted')->default(0);
            $table->unsignedSmallInteger('edited')->default(0);
            $table->unique(['provider_id', 'date']);
        });

        Schema::table('providers', function (Blueprint $table) {
            // When we started following its posts: deletions count only after two weeks of it
            $table->timestamp('telegram_posts_since')->nullable()->after('telegram_failures');
            // Indicators an admin hid on this channel's page (engagement, deleted, activity)
            $table->json('telegram_hidden_insights')->nullable()->after('telegram_posts_since');
        });
    }

    public function down(): void
    {
        Schema::table('providers', fn (Blueprint $table) => $table->dropColumn(['telegram_posts_since', 'telegram_hidden_insights']));
        Schema::dropIfExists('telegram_post_days');
        Schema::dropIfExists('telegram_posts');
    }
};
