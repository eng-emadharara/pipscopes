<?php

use App\Support\SignupGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The inbox an address really lands in (Gmail dots and +tags removed), to stop one inbox opening many accounts
            $table->string('email_canonical')->nullable()->index();
            $table->string('signup_ip', 45)->nullable()->index();
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->string('ip', 45)->nullable()->index();
            // Why a review was held for moderation instead of published (App\Support\SignupGuard::holdReason)
            $table->string('hold_reason', 40)->nullable();
        });

        DB::table('users')->whereNotNull('email')->orderBy('id')->each(function ($user) {
            DB::table('users')->where('id', $user->id)->update(['email_canonical' => SignupGuard::canonical($user->email)]);
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['ip']);
            $table->dropColumn(['ip', 'hold_reason']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email_canonical']);
            $table->dropIndex(['signup_ip']);
            $table->dropColumn(['email_canonical', 'signup_ip']);
        });
    }
};
