<?php

namespace App\Filament\Resources\ProviderResource\RelationManagers;

use App\Models\ProviderChange;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/** Read-only log of what the channel owner changed through the owner portal. */
class ChangesRelationManager extends RelationManager
{
    protected static string $relationship = 'changeLog';

    protected static ?string $title = 'سجل تعديلات صاحب القناة';

    protected static ?string $icon = 'heroicon-o-clock';

    public static function getBadge(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->changeLog()->count() ?: null;
    }

    protected static ?string $modelLabel = 'تعديل';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function describe(ProviderChange $change): HtmlString
    {
        $lines = collect($change->diff)->map(function (array $diff, string $field) {
            $format = fn ($value) => match (true) {
                $value === null || $value === '' || $value === [] => '—',
                is_array($value) => collect($value)->map(fn ($v) => is_array($v) ? implode(' ', array_filter($v)) : $v)->join('، '),
                default => \Illuminate\Support\Str::limit((string) $value, 120),
            };
            $label = ProviderChange::LABELS[$field] ?? $field;
            $warn = in_array($field, ProviderChange::SENSITIVE, true) ? '⚠️ ' : '';

            return '<div>'.$warn.'<strong>'.e($label).'</strong>: <span style="text-decoration:line-through;opacity:.6">'.e($format($diff['old'])).'</span> ← <span dir="auto">'.e($format($diff['new'])).'</span></div>';
        });

        return new HtmlString($lines->join(''));
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('التاريخ')->dateTime('Y-m-d H:i')->description(fn (ProviderChange $r) => $r->created_at->diffForHumans()),
                Tables\Columns\TextColumn::make('owner.name')->label('بواسطة')->placeholder('حساب محذوف'),
                Tables\Columns\TextColumn::make('diff')->label('ما الذي تغيّر')->wrap()
                    ->formatStateUsing(fn ($state, ProviderChange $record) => static::describe($record)),
            ]);
    }
}
