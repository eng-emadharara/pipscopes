<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What each followed post is (App\Support\PostKind): trade, result claim, ad or other. Still no text stored. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_posts', function (Blueprint $table) {
            $table->string('kind', 8)->nullable()->after('edited');
            // Brokers whose sign-up links the post carries, comma-separated
            $table->string('brokers')->nullable()->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_posts', fn (Blueprint $table) => $table->dropColumn(['kind', 'brokers']));
    }
};
