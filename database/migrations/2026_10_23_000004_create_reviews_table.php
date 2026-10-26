<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title');
            $table->text('body');
            $table->boolean('show_name')->default(true);

            // Sub-ratings per criterion (1-5) used for the rating analysis
            $table->unsignedTinyInteger('rating_accuracy')->nullable();
            $table->unsignedTinyInteger('rating_transparency')->nullable();
            $table->unsignedTinyInteger('rating_support')->nullable();
            $table->unsignedTinyInteger('rating_value')->nullable();

            // Reason tags (keys from config/trust.php)
            $table->json('pros')->nullable();
            $table->json('cons')->nullable();
            $table->string('experience_duration')->nullable();

            $table->string('status')->default('approved');
            $table->text('admin_note')->nullable();
            $table->text('provider_reply')->nullable();
            $table->timestamps();

            $table->unique(['provider_id', 'user_id']);
            $table->index(['provider_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
