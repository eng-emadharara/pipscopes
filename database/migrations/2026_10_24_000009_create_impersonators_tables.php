<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Additional official accounts besides the standard social fields: [{label, url}]
        Schema::table('providers', function (Blueprint $table) {
            $table->json('extra_links')->nullable()->after('whatsapp');
        });

        // One row per fake account per provider; repeated reports raise reports_count
        Schema::create('impersonators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('platform');
            $table->string('handle');
            $table->string('account_key');
            $table->text('public_note')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('reports_count')->default(0);
            $table->timestamps();

            $table->unique(['provider_id', 'account_key']);
            $table->index(['account_key', 'status']);
        });

        Schema::create('impersonator_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('impersonator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('voter_key', 80);
            $table->text('details')->nullable();
            $table->string('contact')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->unique(['impersonator_id', 'voter_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonator_reports');
        Schema::dropIfExists('impersonators');
        Schema::table('providers', fn (Blueprint $table) => $table->dropColumn('extra_links'));
    }
};
