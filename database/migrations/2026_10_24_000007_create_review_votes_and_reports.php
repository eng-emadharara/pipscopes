<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // voter_key identifies one voter: "u:{user id}" for members, "g:{hashed IP}" for guests
        Schema::create('review_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('voter_key', 80);
            $table->timestamps();

            $table->unique(['review_id', 'voter_key']);
        });

        Schema::create('review_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('voter_key', 80);
            $table->string('reason');
            $table->text('details')->nullable();
            $table->string('status')->default('open');
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->unique(['review_id', 'voter_key']);
            $table->index('status');
        });

        // Cached counters: helpful votes, and reports still waiting for a decision
        Schema::table('reviews', function (Blueprint $table) {
            $table->unsignedInteger('helpful_count')->default(0)->after('provider_reply');
            $table->unsignedInteger('open_reports_count')->default(0)->after('helpful_count');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', fn (Blueprint $table) => $table->dropColumn(['helpful_count', 'open_reports_count']));
        Schema::dropIfExists('review_reports');
        Schema::dropIfExists('review_votes');
    }
};
