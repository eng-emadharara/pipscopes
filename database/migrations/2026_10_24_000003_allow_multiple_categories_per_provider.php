<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_provider', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->primary(['category_id', 'provider_id']);
        });

        DB::table('providers')->whereNotNull('category_id')->orderBy('id')->each(function ($provider) {
            DB::table('category_provider')->insert(['category_id' => $provider->category_id, 'provider_id' => $provider->id]);
        });

        Schema::table('providers', fn (Blueprint $table) => $table->dropConstrainedForeignId('category_id'));

        Schema::table('provider_submissions', fn (Blueprint $table) => $table->json('category_ids')->nullable()->after('relation'));

        DB::table('provider_submissions')->whereNotNull('category_id')->orderBy('id')->each(function ($submission) {
            DB::table('provider_submissions')->where('id', $submission->id)
                ->update(['category_ids' => json_encode([(int) $submission->category_id])]);
        });

        Schema::table('provider_submissions', fn (Blueprint $table) => $table->dropConstrainedForeignId('category_id'));
    }

    public function down(): void
    {
        Schema::table('providers', fn (Blueprint $table) => $table->foreignId('category_id')->nullable()->after('id')->constrained()->nullOnDelete());
        Schema::table('provider_submissions', fn (Blueprint $table) => $table->foreignId('category_id')->nullable()->after('relation')->constrained()->nullOnDelete());

        // Keep the first category (by category sort order) when going back to a single one
        DB::table('category_provider')
            ->join('categories', 'categories.id', '=', 'category_provider.category_id')
            ->orderBy('categories.sort_order')
            ->get(['category_provider.provider_id', 'category_provider.category_id'])
            ->groupBy('provider_id')
            ->each(function ($rows, $providerId) {
                DB::table('providers')->where('id', $providerId)->update(['category_id' => $rows->first()->category_id]);
            });

        DB::table('provider_submissions')->whereNotNull('category_ids')->orderBy('id')->each(function ($submission) {
            $first = json_decode($submission->category_ids, true)[0] ?? null;
            DB::table('provider_submissions')->where('id', $submission->id)->update(['category_id' => $first]);
        });

        Schema::table('provider_submissions', fn (Blueprint $table) => $table->dropColumn('category_ids'));
        Schema::dropIfExists('category_provider');
    }
};
