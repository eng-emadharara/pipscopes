<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('submitter_name');
            $table->string('submitter_contact');
            $table->string('relation')->default('owner');

            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('service_type');
            $table->string('markets')->nullable();
            $table->string('pricing')->nullable();
            $table->text('description')->nullable();
            $table->string('telegram_url')->nullable();
            $table->string('website_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('youtube_url')->nullable();
            $table->string('tiktok_url')->nullable();
            $table->string('facebook_url')->nullable();

            $table->string('status')->default('new');
            $table->text('admin_notes')->nullable();
            $table->foreignId('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_submissions');
    }
};
