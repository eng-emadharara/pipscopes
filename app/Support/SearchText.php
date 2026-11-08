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

    /** Arabic letters as the consonant they sound like; long vowels, alef, ain and hamza carry none */
    protected const ARABIC_SOUNDS = [
        'ب' => 'b', 'پ' => 'b', 'ت' => 't', 'ث' => 't', 'ط' => 't', 'ج' => 'j', 'غ' => 'j', 'ح' => 'h', 'ه' => 'h', 'ة' => 'h',
        'خ' => 'k', 'ق' => 'k', 'ك' => 'k', 'د' => 'd', 'ذ' => 'd', 'ض' => 'd', 'ظ' => 'd', 'ر' => 'r', 'ز' => 'z',
        'س' => 's', 'ص' => 's', 'ش' => 'x', 'ف' => 'f', 'ڤ' => 'f', 'ل' => 'l', 'م' => 'm', 'ن' => 'n',
    ];

    /** Latin spellings of the same sounds; vowels, w, y and h-after-a-consonant carry none */
    protected const LATIN_SOUNDS = [
        'tch' => 'x', 'sh' => 'x', 'ch' => 'x', 'kh' => 'k', 'gh' => 'j', 'th' => 't', 'dh' => 'd', 'ph' => 'f',
        'q' => 'k', 'c' => 'k', 'g' => 'j', 'v' => 'f', 'p' => 'b', 'x' => 'ks',
    ];

    /**
     * How a word sounds, written the same whichever alphabet it came in: "قمر" and "Qamar" are both "kmr",
     * "ريتشماما" and "Richmama" both "rxm". Only consonants, doubled ones once. Empty for words with no letters.
     */
    public static function soundKey(string $word): string
    {
        $word = static::normalize($word);

        if (preg_match('/\p{Arabic}/u', $word)) {
            $word = str_replace('تش', 'ش', $word);
            $key = implode('', array_map(fn ($c) => self::ARABIC_SOUNDS[$c] ?? '', mb_str_split($word)));
        } else {
            $word = strtr(preg_replace('/[^a-z]/', '', $word), self::LATIN_SOUNDS);
            $key = preg_replace('/[aeiouyw]/', '', $word);
        }

        return preg_replace('/(.)\1+/', '$1', $key);
    }

    /** The sound keys of every word in a text, each with a leading space so a search can match the start of one */
    public static function soundKeys(?string $text): string
    {
        $words = preg_split('/[^\p{L}]+/u', static::normalize($text), -1, PREG_SPLIT_NO_EMPTY);
        $keys = array_unique(array_filter(array_map(static::soundKey(...), $words), fn ($k) => strlen($k) >= 2));

        return $keys ? ' '.implode(' ', $keys) : '';
    }

    /**
     * Goes after "LIKE ?". "!" rather than a backslash: in MySQL a backslash inside ESCAPE '\'
     * escapes the closing quote and breaks the query (SQLite accepts it, so it only failed live).
     */
    public const LIKE_ESCAPE = "ESCAPE '!'";

    /** Escapes LIKE wildcards (for LIKE_ESCAPE) so "_" in handles like demo_channel matches literally. */
    public static function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    /** "%word%" with the word's own wildcards escaped. */
    public static function like(string $word): string
    {
        return '%'.static::escapeLike($word).'%';
    }
}
