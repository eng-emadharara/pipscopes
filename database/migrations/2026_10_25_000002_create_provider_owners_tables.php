<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Login accounts for channel owners, created by admins (there is no self sign-up)
        Schema::create('provider_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // What an owner changed in their channel details, for admins to review
        Schema::create('provider_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_owner_id')->nullable()->constrained()->nullOnDelete();
            $table->json('diff');
            $table->timestamps();
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->timestamp('provider_replied_at')->nullable()->after('provider_reply');
            $table->foreignId('provider_reply_owner_id')->nullable()->after('provider_replied_at')
                ->constrained('provider_owners')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('provider_reply_owner_id');
            $table->dropColumn('provider_replied_at');
        });
        Schema::dropIfExists('provider_changes');
        Schema::dropIfExists('provider_owners');
    }
};
