<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The homepage's sections under the hero: their order, on/off and titles
 * (Settings → أقسام الصفحة الرئيسية). Stored as the "home_layout" setting, a list of
 * {key, enabled, title: {locale: text}, limit}; sections added later join at their default place.
 */
class HomeSections
{
    /** key => [admin label, default title (null: the section has none), default limit (null: none)] */
    public const SECTIONS = [
        'categories' => ['التصنيفات', null, null],
        'picks' => ['اختيارات الفريق', 'اختيارات فريق PipScopes', null],
        'account_check' => ['أداة "هل هذا الحساب حقيقي؟"', null, null],
        'top_rated' => ['الأعلى تقييماً', 'الأعلى تقييماً', 8],
        'alerts' => ['تنبيهات (حسابات منتحلة وقنوات عليها مؤشرات خطيرة)', 'تنبيهات', null],
        'latest_reviews' => ['أحدث التقييمات', 'أحدث التقييمات', 6],
        'trust' => ['كيف نضمن المصداقية', 'كيف نضمن المصداقية', null],
        'telegram_join' => ['دعوة للانضمام لقناة تيليجرام', null, null],
        'cta' => ['دعوة أصحاب الخدمات لإضافة قنواتهم', null, null],
    ];

    /** Choices for the sections that have a limit */
    public const LIMITS = [4, 8, 12];

    /** @return list<array{key: string, enabled: bool, title: array<string, string>, limit: ?int}> */
    public static function layout(): array
    {
        $saved = collect(Setting::get('home_layout', []))->keyBy('key');
        // Before ordering existed only four sections could be switched off: keep those choices
        $legacy = (array) Setting::get('home_sections', []);

        $ordered = $saved->keys()->filter(fn ($key) => isset(self::SECTIONS[$key]))->values()->all();
        // Sections not saved yet slot in right after the one they follow by default
        foreach (array_keys(self::SECTIONS) as $i => $key) {
            if (! in_array($key, $ordered, true)) {
                $before = array_keys(self::SECTIONS)[$i - 1] ?? null;
                $at = $before !== null && ($p = array_search($before, $ordered, true)) !== false ? $p + 1 : $i;
                array_splice($ordered, min($at, count($ordered)), 0, [$key]);
            }
        }

        return array_map(function (string $key) use ($saved, $legacy) {
            $row = $saved->get($key, []);

            return [
                'key' => $key,
                'enabled' => (bool) ($row['enabled'] ?? $legacy[$key] ?? true),
                'title' => array_filter((array) ($row['title'] ?? [])),
                'limit' => in_array((int) ($row['limit'] ?? 0), self::LIMITS, true) ? (int) $row['limit'] : self::SECTIONS[$key][2],
            ];
        }, $ordered);
    }

    public static function enabled(string $key): bool
    {
        return collect(self::layout())->firstWhere('key', $key)['enabled'] ?? false;
    }

    /** @return array<string, bool> */
    public static function all(): array
    {
        return collect(self::layout())->mapWithKeys(fn ($s) => [$s['key'] => $s['enabled']])->all();
    }

    /** A section's heading: the admin's text in this language, then Arabic, then the default. */
    public static function title(array $section): string
    {
        $base = array_key_first(config('trust.locales'));

        return $section['title'][app()->getLocale()] ?? $section['title'][$base] ?? __(self::SECTIONS[$section['key']][1] ?? '');
    }

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
}
