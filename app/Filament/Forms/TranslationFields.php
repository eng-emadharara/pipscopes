<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * Form fields for translatable models. Base-language values live in the model's normal
 * columns; other languages are stored in its `translations` JSON column as
 * translations.{locale}.{field}.
 *
 * Field options: label, textarea, rows, rich, toolbar (rich editor buttons), maxLength, and
 * required / helper / placeholder (applied to the base language only).
 */
class TranslationFields
{
    /**
     * One tab per language, base language first: every language is edited the same way.
     *
     * @param  array<string, array<string, mixed>>  $fields
     */
    public static function tabs(array $fields): Tabs
    {
        $baseCode = array_key_first(config('trust.locales'));

        $tabs = collect(config('trust.locales'))
            ->map(function (array $meta, string $code) use ($fields, $baseCode) {
                $isBase = $code === $baseCode;

                return Tabs\Tab::make($code)->label($meta['name'])
                    ->icon($isBase ? 'heroicon-o-star' : 'heroicon-o-language')
                    ->schema(collect($fields)->map(fn (array $field, string $name) => static::field(
                        $isBase ? $name : "translations.$code.$name",
                        $field,
                        $meta['dir'],
                        $isBase,
                    ))->values()->all());
            })
            ->values()
            ->all();

        return Tabs::make('اللغات')->tabs($tabs);
    }

    /** @param  array<string, mixed>  $field */
    protected static function field(string $path, array $field, string $dir, bool $isBase = false): Component
    {
        if ($field['rich'] ?? false) {
            $component = RichEditor::make($path)
                ->when($field['toolbar'] ?? null, fn (RichEditor $editor, array $buttons) => $editor->toolbarButtons($buttons));
        } elseif ($field['textarea'] ?? false) {
            $component = Textarea::make($path)->rows($field['rows'] ?? 4);
        } else {
            $component = TextInput::make($path);
        }

        if (isset($field['maxLength']) && ! ($field['rich'] ?? false)) {
            $component->maxLength($field['maxLength']);
        }

        if ($isBase) {
            $component->required($field['required'] ?? false)
                ->helperText($field['helper'] ?? null);

            // Placeholders are written in the base language, so only that tab shows them
            if (isset($field['placeholder']) && ! ($field['rich'] ?? false)) {
                $component->placeholder($field['placeholder']);
            }
        } else {
            $component->helperText('اختياري: إذا تُرك فارغاً يظهر النص العربي.');
        }

        return $component->label($field['label'])->extraInputAttributes(['dir' => $dir]);
    }
}
