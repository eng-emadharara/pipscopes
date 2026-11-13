<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "I was scammed": reports from members (with private proofs), gathered into one case per
 * account / wallet / broker, and what the team did about each case (shown to its reporters).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scam_cases', function (Blueprint $table) {
            $table->id();
            // What the reports are about: an account (AccountHandle key, e.g. "telegram:goldpro"), a wallet or a broker
            $table->string('target_type', 20);
            $table->string('target_key')->unique();
            $table->string('target_display');
            $table->foreignId('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_public')->default(false);
            $table->text('public_summary')->nullable();
            // Confirmed reports only, and their losses in approximate US dollars
            $table->unsignedInteger('reports_count')->default(0);
            $table->decimal('total_usd', 14, 2)->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['provider_id', 'is_public']);
        });

        Schema::create('scam_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scam_case_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scam_type', 30);
            $table->string('payment_method', 20);
            $table->date('paid_at')->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->string('currency', 5)->nullable();
            $table->decimal('amount_usd', 14, 2)->nullable();
            $table->string('account')->nullable();
            $table->string('wallet')->nullable();
            $table->string('broker')->nullable();
            $table->text('story');
            $table->json('proofs')->nullable();
            $table->string('contact')->nullable();
            $table->boolean('consent_share')->default(false);
            $table->boolean('consent_contact')->default(false);
            $table->string('status', 20)->default('pending');
            $table->text('admin_note')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('scam_case_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scam_case_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->text('note');
            $table->date('done_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scam_case_actions');
        Schema::dropIfExists('scam_reports');
        Schema::dropIfExists('scam_cases');
    }
};
