<?php

namespace App\Support;

/**
 * Prepares text for GD, which draws glyphs one by one, left to right, with no shaping:
 *  1. each Arabic letter is replaced by its contextual presentation form (isolated,
 *     final, initial, medial) and lam + alef become a ligature;
 *  2. the string is put in visual order for a right-to-left line, keeping runs of
 *     Latin letters and digits ("MT4/MT5", "4.6", "@channel") in their own order.
 * Text without Arabic letters is returned unchanged.
 */
class ArabicShaper
{
    /** letter => [isolated, final, initial, medial]; letters with 2 forms only join to the previous letter */
    protected const FORMS = [
        0x0621 => [0xFE80],
        0x0622 => [0xFE81, 0xFE82], 0x0623 => [0xFE83, 0xFE84], 0x0624 => [0xFE85, 0xFE86],
        0x0625 => [0xFE87, 0xFE88], 0x0626 => [0xFE89, 0xFE8A, 0xFE8B, 0xFE8C], 0x0627 => [0xFE8D, 0xFE8E],
        0x0628 => [0xFE8F, 0xFE90, 0xFE91, 0xFE92], 0x0629 => [0xFE93, 0xFE94],
        0x062A => [0xFE95, 0xFE96, 0xFE97, 0xFE98], 0x062B => [0xFE99, 0xFE9A, 0xFE9B, 0xFE9C],
        0x062C => [0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0], 0x062D => [0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4],
        0x062E => [0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8], 0x062F => [0xFEA9, 0xFEAA], 0x0630 => [0xFEAB, 0xFEAC],
        0x0631 => [0xFEAD, 0xFEAE], 0x0632 => [0xFEAF, 0xFEB0],
        0x0633 => [0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4], 0x0634 => [0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8],
        0x0635 => [0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC], 0x0636 => [0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0],
        0x0637 => [0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4], 0x0638 => [0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8],
        0x0639 => [0xFEC9, 0xFECA, 0xFECB, 0xFECC], 0x063A => [0xFECD, 0xFECE, 0xFECF, 0xFED0],
        0x0641 => [0xFED1, 0xFED2, 0xFED3, 0xFED4], 0x0642 => [0xFED5, 0xFED6, 0xFED7, 0xFED8],
        0x0643 => [0xFED9, 0xFEDA, 0xFEDB, 0xFEDC], 0x0644 => [0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0],
        0x0645 => [0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4], 0x0646 => [0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8],
        0x0647 => [0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC], 0x0648 => [0xFEED, 0xFEEE], 0x0649 => [0xFEEF, 0xFEF0],
        0x064A => [0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4],
        // Persian/Urdu letters that show up in names
        0x067E => [0xFB56, 0xFB57, 0xFB58, 0xFB59], 0x0686 => [0xFB7A, 0xFB7B, 0xFB7C, 0xFB7D],
        0x06A9 => [0xFB8E, 0xFB8F, 0xFB90, 0xFB91], 0x06AF => [0xFB92, 0xFB93, 0xFB94, 0xFB95],
        0x06CC => [0xFBFC, 0xFBFD, 0xFBFE, 0xFBFF],
    ];

    /** alef variant => [lam-alef isolated, lam-alef final] */
    protected const LAM_ALEF = [
        0x0622 => [0xFEF5, 0xFEF6], 0x0623 => [0xFEF7, 0xFEF8], 0x0625 => [0xFEF9, 0xFEFA], 0x0627 => [0xFEFB, 0xFEFC],
    ];

    protected const TATWEEL = 0x0640;

    protected const MIRRORED = ['(' => ')', ')' => '(', '[' => ']', ']' => '[', '{' => '}', '}' => '{', '«' => '»', '»' => '«', '<' => '>', '>' => '<'];

    public static function hasArabic(string $text): bool
    {
        return (bool) preg_match('/\p{Arabic}/u', $text);
    }

    /** Shaped and in visual order, ready for imagettftext(). */
    public static function forImage(string $text): string
    {
        if (! static::hasArabic($text)) {
            return $text;
        }

        return static::visualOrder(static::shape($text));
    }

    public static function shape(string $text): string
    {
        // Diacritics are dropped: they are optional in Arabic and GD can't position them
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $text);
        $codes = array_map('mb_ord', mb_str_split($text));
        $count = count($codes);
        $out = [];

        for ($i = 0; $i < $count; $i++) {
            $code = $codes[$i];

            if (! isset(self::FORMS[$code]) && $code !== self::TATWEEL) {
                $out[] = $code;

                continue;
            }

            $joinsPrevious = $i > 0 && static::joinsForward($codes[$i - 1]);

            // Lam followed by an alef becomes a single ligature
            if ($code === 0x0644 && isset($codes[$i + 1], self::LAM_ALEF[$codes[$i + 1]])) {
                $out[] = self::LAM_ALEF[$codes[$i + 1]][$joinsPrevious ? 1 : 0];
                $i++;

                continue;
            }

            if ($code === self::TATWEEL) {
                $out[] = $code;

                continue;
            }

            $forms = self::FORMS[$code];
            $joinsNext = count($forms) === 4 && isset($codes[$i + 1])
                && (isset(self::FORMS[$codes[$i + 1]]) || $codes[$i + 1] === self::TATWEEL);

            $out[] = match (true) {
                $joinsPrevious && $joinsNext => $forms[3],
                $joinsPrevious => $forms[1] ?? $forms[0],
                $joinsNext => $forms[2],
                default => $forms[0],
            };
        }

        return implode('', array_map('mb_chr', $out));
    }

    /** Whether a letter connects to the letter after it (dual-joining letters and tatweel). */
    protected static function joinsForward(int $code): bool
    {
        return $code === self::TATWEEL || (isset(self::FORMS[$code]) && count(self::FORMS[$code]) === 4);
    }

    /** Reverses a right-to-left line for left-to-right drawing, keeping Latin/number runs intact. */
    protected static function visualOrder(string $shaped): string
    {
        // Runs of Latin letters/digits, with joiners like "." "/" "@" "_" "-" inside them
        preg_match_all('/[@#]?[A-Za-z0-9\x{0660}-\x{0669}](?:[A-Za-z0-9\x{0660}-\x{0669}.,:\/@_\-+%#\']*[A-Za-z0-9\x{0660}-\x{0669}%])?|./us', $shaped, $matches);

        $tokens = array_reverse($matches[0]);

        return implode('', array_map(fn ($t) => self::MIRRORED[$t] ?? $t, $tokens));
    }
}
