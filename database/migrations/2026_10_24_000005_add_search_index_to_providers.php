<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Normalized text of everything searchable about a provider (see Provider::buildSearchIndex)
        Schema::table('providers', function (Blueprint $table) {
            $table->text('search_index')->nullable();
        });

        Artisan::call('providers:reindex');
    }

    public function down(): void
    {
        Schema::table('providers', fn (Blueprint $table) => $table->dropColumn('search_index'));
    }
};
