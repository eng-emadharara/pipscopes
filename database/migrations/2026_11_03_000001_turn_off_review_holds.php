<?php

use App\Models\Setting;
use App\Support\SignupGuard;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    // Reviews publish straight away for now, with alerts to the admins; holds can be switched back on in Admin → حماية التسجيل
    public function up(): void
    {
        if ($saved = Setting::get(SignupGuard::SETTING)) {
            Setting::put([SignupGuard::SETTING => [...$saved, 'hold_new_reviews' => false, 'hold_same_ip' => false]]);
        }
    }

    public function down(): void
    {
        //
    }
};
