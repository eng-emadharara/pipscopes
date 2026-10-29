<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** "Team picks" on the homepage: channels the admin highlights, in the order they choose. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_picks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0)->index();
            $table->string('note', 160)->nullable();     // why it was picked, shown on its card
            $table->json('translations')->nullable();     // the note in other languages
            $table->date('starts_at')->nullable();        // shown from / until (both optional)
            $table->date('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_picks');
    }
};
