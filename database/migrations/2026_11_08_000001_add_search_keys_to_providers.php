<?php

use App\Models\Provider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How the name and handle sound, so "قمر" finds "Qamar" and back (App\Support\SearchText::soundKey)
        Schema::table('providers', fn (Blueprint $table) => $table->text('search_keys')->nullable());

        // Straight to the table so updated_at stays as it was (the sitemap and share images go by it)
        Provider::query()->orderBy('id')->each(
            fn (Provider $provider) => DB::table('providers')->where('id', $provider->id)->update(['search_keys' => $provider->buildSearchKeys()])
        );
    }

    public function down(): void
    {
        Schema::table('providers', fn (Blueprint $table) => $table->dropColumn('search_keys'));
    }
};
