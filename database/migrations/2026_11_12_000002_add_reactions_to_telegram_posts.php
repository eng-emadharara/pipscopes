<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Each followed post's emoji reactions, added up (null when it shows none) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_posts', fn (Blueprint $table) => $table->unsignedInteger('reactions')->nullable()->after('views'));
    }

    public function down(): void
    {
        Schema::table('telegram_posts', fn (Blueprint $table) => $table->dropColumn('reactions'));
    }
};
