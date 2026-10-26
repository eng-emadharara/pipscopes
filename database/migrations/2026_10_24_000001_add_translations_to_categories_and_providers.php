<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // {"en": {"name": "...", "description": "..."}} - base-language values stay in the normal columns
        Schema::table('categories', function (Blueprint $table) {
            $table->json('translations')->nullable()->after('description');
        });

        Schema::table('providers', function (Blueprint $table) {
            $table->json('translations')->nullable()->after('bio');
        });
    }

    public function down(): void
    {
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn('translations'));
        Schema::table('providers', fn (Blueprint $table) => $table->dropColumn('translations'));
    }
};
