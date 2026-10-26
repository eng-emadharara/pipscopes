<?php

namespace App\Models\Concerns;

/**
 * Base-language values live in the model's normal columns; other languages are
 * stored in a `translations` JSON column as {locale: {field: value}}.
 * Models list their translatable fields in `$translatable`.
 */
trait HasTranslations
{
    public function initializeHasTranslations(): void
    {
        $this->mergeCasts(['translations' => 'array']);
        $this->mergeFillable(['translations']);
    }

    /** Field value in the current locale, falling back to the base-language column. */
    public function tr(string $field, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        return filled($this->translations[$locale][$field] ?? null)
            ? $this->translations[$locale][$field]
            : $this->getAttribute($field);
    }

    /** @return list<string> */
    public static function translationLocales(): array
    {
        return array_slice(array_keys(config('trust.locales')), 1);
    }
}
