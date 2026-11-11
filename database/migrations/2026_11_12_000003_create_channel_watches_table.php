<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Bot chats following a channel, to be told when something happens to it (App\Support\ChannelWatch) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_watches', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('chat_id')->index();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['chat_id', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_watches');
    }
};
