<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            // Open to visitors during pre-launch, at the owner's request (App\Support\Launch::earlyOpen)
            $table->boolean('is_early_open')->default(false)->index();
        });

        Schema::table('reviews', function (Blueprint $table) {
            // Written on an early-open channel before launch; shown to admins only
            $table->boolean('is_early')->default(false)->index();
            // Why this review looks suspicious (App\Support\EarlyReviewAlerts::REASONS)
            $table->json('alerts')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['is_early']);
            $table->dropColumn(['is_early', 'alerts']);
        });

        Schema::table('providers', function (Blueprint $table) {
            $table->dropIndex(['is_early_open']);
            $table->dropColumn('is_early_open');
        });
    }
};
