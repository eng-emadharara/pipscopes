<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\Site;
use Illuminate\Database\Seeder;

/**
 * Starting values for Admin → إعدادات الموقع, written from the site's own content.
 * Only empty fields are filled, so re-running it never overwrites what the admin changed.
 */
class SiteSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $footerAbout = 'منصة مستقلة لتقييم قنوات التوصيات ومقدمي خدمات نسخ الصفقات والتداول، تعتمد على تجارب المستخدمين الحقيقية.';
        $disclaimer = 'التقييمات تعبّر عن آراء أصحابها. التداول ينطوي على مخاطر عالية، ولا يُعد أي محتوى هنا نصيحة مالية.';

        $defaults = [
            'name' => ['ar' => 'TrustTrader', 'en' => 'TrustTrader'],
            'meta_description' => [
                'ar' => 'اقرأ تقييمات حقيقية لقنوات التوصيات وخدمات نسخ الصفقات قبل أن تشترك. تحقّق من الحسابات الرسمية، واكشف المنتحلين، وشارك تجربتك بشفافية.',
                'en' => 'Read real reviews of trading signal channels and copy-trading services before you subscribe. Check official accounts, spot impersonators and share your experience.',
            ],
            'meta_keywords' => [
                'ar' => 'تقييم قنوات التوصيات, قنوات توصيات تيليجرام, نسخ الصفقات, توصيات فوركس, توصيات الذهب, توصيات كريبتو, كشف النصابين, تقييمات المتداولين',
                'en' => 'signal channel reviews, telegram trading signals, copy trading reviews, forex signals, gold signals, crypto signals, trading scam check',
            ],
            'footer_about' => ['ar' => $footerAbout, 'en' => __($footerAbout, [], 'en')],
            'disclaimer' => ['ar' => $disclaimer, 'en' => __($disclaimer, [], 'en')],
            'show_name_with_logo' => true,
            'theme_color' => '#191919',
            'noindex' => false,
        ];

        $current = Site::all();
        $merged = $current;
        foreach ($defaults as $key => $value) {
            $merged[$key] = is_array($value)
                ? array_merge($value, array_filter((array) ($current[$key] ?? []), 'filled'))
                : ($current[$key] ?? $value);
        }

        Setting::put([Site::KEY => $merged]);
    }
}
