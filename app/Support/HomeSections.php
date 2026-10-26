<?php

namespace App\Support;

use App\Models\Setting;

/** Optional homepage blocks the admin can switch on/off (Settings → أقسام الصفحة الرئيسية). */
class HomeSections
{
    /** key => admin label */
    public const SECTIONS = [
        'account_check' => 'أداة "هل هذا الحساب حقيقي؟"',
        'alerts' => 'تنبيهات (حسابات منتحلة وقنوات عليها مؤشرات خطيرة)',
        'trust' => 'كيف نضمن المصداقية',
        'telegram_join' => 'دعوة للانضمام لقناة تيليجرام',
    ];

    /** Admin-edited texts of the Telegram section: telegram_join.{title|text}.{locale} */
    public static function telegramText(string $field, string $default): string
    {
        $values = (array) data_get(Setting::get('telegram_join', []), $field, []);
        $base = array_key_first(config('trust.locales'));

        foreach ([app()->getLocale(), $base] as $locale) {
            if (filled($values[$locale] ?? null)) {
                return $values[$locale];
            }
        }

        return $default;
    }

    public static function enabled(string $key): bool
    {
        // New sections are visible until the admin turns them off
        return (bool) (Setting::get('home_sections', [])[$key] ?? true);
    }

    /** @return array<string, bool> */
    public static function all(): array
    {
        return collect(self::SECTIONS)->mapWithKeys(fn ($label, $key) => [$key => self::enabled($key)])->all();
    }
}
