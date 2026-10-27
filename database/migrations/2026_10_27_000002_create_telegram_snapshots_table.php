<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily Telegram numbers per channel (App\Services\TelegramRefresh). A row is written only when a
 * number changed, and old rows are merged by telegram:compact: daily for 90 days, weekly for a
 * year, monthly after that, so each channel keeps roughly 150 rows however long it's tracked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->date('date');                       // the day, or the first day of the week / month
            $table->char('period', 1)->default('d');    // d = day, w = week, m = month
            $table->unsignedInteger('subscribers')->nullable();
            $table->unsignedInteger('avg_views')->nullable();
            $table->unique(['provider_id', 'period', 'date']);
        });

        Schema::table('providers', function (Blueprint $table) {
            $table->timestamp('telegram_checked_at')->nullable()->index()->after('telegram_data');
            // channel | private | not_found | not_channel | unreachable: the last read's outcome
            $table->string('telegram_status')->nullable()->after('telegram_checked_at');
            $table->unsignedSmallInteger('telegram_failures')->default(0)->after('telegram_status');
        });
    }

    public function down(): void
    {
        Schema::table('providers', fn (Blueprint $table) => $table->dropColumn(['telegram_checked_at', 'telegram_status', 'telegram_failures']));
        Schema::dropIfExists('telegram_snapshots');
    }
};
