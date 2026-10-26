<?php

namespace App\Support;

/**
 * Normalizes text for forgiving search: lowercase, Arabic letter variants folded
 * (أ/إ/آ → ا, ة → ه, ى → ي …), diacritics and tatweel removed, Arabic digits → Latin.
 * The same function is applied to stored text and to the user's query.
 */
class SearchText
{
    public static function normalize(?string $text): string
    {
        $text = mb_strtolower((string) $text);

        // Diacritics (tashkeel), superscript alef and tatweel
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text);

        $text = strtr($text, [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Query words, normalized; a leading "@" (Telegram handle) is dropped.
     *
     * @return list<string>
     */
    public static function words(string $query): array
    {
        $words = explode(' ', static::normalize($query));

        return array_values(array_filter(array_map(fn ($w) => ltrim($w, '@'), $words), fn ($w) => $w !== ''));
    }

    /** Escapes LIKE wildcards so "_" in handles like demo_channel matches literally. */
    public static function like(string $word): string
    {
        return '%'.addcslashes($word, '%_\\').'%';
    }
}
