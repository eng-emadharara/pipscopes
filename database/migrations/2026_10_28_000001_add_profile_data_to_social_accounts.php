<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Everything the provider sends back on sign-in, kept as-is, plus the OAuth tokens (stored encrypted)
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->string('name')->nullable()->after('email');
            $table->json('raw_data')->nullable()->after('avatar_url');
            $table->text('token')->nullable()->after('raw_data');
            $table->text('refresh_token')->nullable()->after('token');
            $table->timestamp('token_expires_at')->nullable()->after('refresh_token');
            $table->text('scopes')->nullable()->after('token_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropColumn(['name', 'raw_data', 'token', 'refresh_token', 'token_expires_at', 'scopes']);
        });
    }
};
