<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The member's own links, keyed like config('trust.socials'): {"twitter_url": "https://x.com/…"}
        Schema::table('users', function (Blueprint $table) {
            $table->json('social_links')->nullable()->after('avatar_url');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('social_links'));
    }
};
