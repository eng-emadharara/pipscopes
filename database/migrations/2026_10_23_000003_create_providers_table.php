<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('service_type');
            $table->string('markets')->nullable();
            $table->string('pricing')->nullable();
            $table->text('bio')->nullable();
            $table->string('avatar')->nullable();

            $table->string('telegram_url')->nullable();
            $table->unsignedInteger('telegram_members')->nullable();
            $table->string('website_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('youtube_url')->nullable();
            $table->string('tiktok_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('whatsapp')->nullable();

            $table->boolean('is_verified')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);

            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'rating_avg']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
