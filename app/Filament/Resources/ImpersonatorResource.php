<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ImpersonatorResource\Pages;
use App\Filament\Resources\ProviderResource\RelationManagers\ImpersonatorsRelationManager;
use App\Models\Impersonator;
use App\Models\Provider;
use App\Support\AccountHandle;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ImpersonatorResource extends Resource
{
    protected static ?string $model = Impersonator::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationGroup = 'الطلبات والتقييمات';

    protected static ?string $modelLabel = 'حساب منتحل';

    protected static ?string $pluralModelLabel = 'الحسابات المنتحلة';

    public static function getNavigationBadge(): ?string
    {
        $pending = Impersonator::where('status', 'pending')->count();

        return $pending ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('provider_id')->label('مقدم الخدمة الذي يتم انتحاله')
                ->relationship('provider', 'name')->searchable()->preload()->required()
                ->hidden(fn ($livewire) => $livewire instanceof ImpersonatorsRelationManager),
            Forms\Components\Select::make('platform')->label('المنصة')
                ->options(collect(AccountHandle::PLATFORMS)->mapWithKeys(fn ($p) => [$p => trans("trust.account_platforms.$p", [], 'ar')]))
                ->default('telegram')->required()
                ->helperText('عند لصق رابط تُحدَّد المنصة تلقائياً من الرابط.'),
            Forms\Components\TextInput::make('handle')->label('رابط الحساب أو اسم المستخدم')
                ->required()->maxLength(255)
                ->extraInputAttributes(['dir' => 'ltr'])
                ->rules([fn (Forms\Get $get, ?Impersonator $record, $livewire) => static::accountRule($get, $record, $livewire)]),
            Forms\Components\Textarea::make('public_note')->label('ملاحظة تظهر للعامة (اختياري)')
                ->placeholder('مثال: يراسل المشتركين على الخاص ويطلب تحويل رسوم VIP')->rows(2)->columnSpanFull(),
            Forms\Components\Select::make('status')->label('الحالة')
                ->options(Impersonator::STATUSES)->default('confirmed')->required()
                ->helperText('فقط الحسابات "المؤكدة" تظهر في صفحة مقدم الخدمة وفي تحذيرات البحث.'),
        ])->columns(2);
    }

    /** Refuses official accounts and duplicates of an already listed impersonator. */
    protected static function accountRule(Forms\Get $get, ?Impersonator $record, $livewire): Closure
    {
        return function (string $attribute, $value, Closure $fail) use ($get, $record, $livewire) {
            $parsed = AccountHandle::parse($value, $get('platform'));
            if (! $parsed) {
                $fail('أدخل رابطاً أو اسم مستخدم صالحاً.');

                return;
            }

            $providerId = $livewire instanceof ImpersonatorsRelationManager ? $livewire->getOwnerRecord()->id : $get('provider_id');
            $provider = Provider::find($providerId);

            if ($provider && in_array($parsed['key'], $provider->officialAccountKeys(), true)) {
                $fail('هذا أحد الحسابات الرسمية لمقدم الخدمة.');
            }

            $duplicate = Impersonator::where('provider_id', $providerId)->where('account_key', $parsed['key'])
                ->when($record, fn ($q) => $q->whereKeyNot($record->id))->exists();
            if ($duplicate) {
                $fail('هذا الحساب مسجّل مسبقاً لهذا المقدم.');
            }
        };
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->columns(3)->schema([
                Infolists\Components\TextEntry::make('provider.name')->label('مقدم الخدمة'),
                Infolists\Components\TextEntry::make('handle')->label('الحساب')->copyable()->extraAttributes(['dir' => 'ltr']),
                Infolists\Components\TextEntry::make('platform')->label('المنصة')
                    ->formatStateUsing(fn ($state) => trans("trust.account_platforms.$state", [], 'ar')),
                Infolists\Components\TextEntry::make('public_note')->label('الملاحظة العامة')->placeholder('—')->columnSpanFull(),
            ]),
            Infolists\Components\RepeatableEntry::make('reports')->label('البلاغات')
                ->schema([
                    Infolists\Components\TextEntry::make('details')->label('كيف تواصل معه')->placeholder('—')->columnSpan(2),
                    Infolists\Components\TextEntry::make('contact')->label('وسيلة تواصل المُبلّغ')->placeholder('—'),
                    Infolists\Components\TextEntry::make('created_at')->label('التاريخ')->since(),
                ])
                ->columns(4)
                ->placeholder('أُضيف يدوياً من الإدارة، لا توجد بلاغات.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('reports_count', 'desc')
            ->columns(static::tableColumns())
            ->filters([
                Tables\Filters\SelectFilter::make('provider')->label('مقدم الخدمة')->relationship('provider', 'name')->searchable()->preload(),
            ])
            ->actions(static::tableActions());
    }

    /** @return array<Tables\Columns\Column> */
    public static function tableColumns(bool $withProvider = true): array
    {
        return array_values(array_filter([
            $withProvider ? Tables\Columns\TextColumn::make('provider.name')->label('مقدم الخدمة')->searchable()->wrap() : null,
            Tables\Columns\TextColumn::make('handle')->label('الحساب المنتحل')->searchable()->copyable()
                ->extraAttributes(['dir' => 'ltr'])
                ->description(fn (Impersonator $r) => trans("trust.account_platforms.{$r->platform}", [], 'ar')),
            Tables\Columns\TextColumn::make('reports_count')->label('البلاغات')->sortable()->badge()
                ->color(fn ($state) => $state >= 3 ? 'danger' : ($state > 0 ? 'warning' : 'gray')),
            Tables\Columns\TextColumn::make('status')->label('الحالة')->badge()
                ->formatStateUsing(fn ($state) => Impersonator::STATUSES[$state] ?? $state)
                ->color(fn ($state) => match ($state) {
                    'confirmed' => 'danger',
                    'pending' => 'warning',
                    default => 'gray',
                }),
            Tables\Columns\TextColumn::make('created_at')->label('أول ظهور')->since()->sortable(),
        ]));
    }

    public static function tableActions(): array
    {
        return [
            Tables\Actions\Action::make('confirm')->label('تأكيد كمنتحل')->icon('heroicon-o-shield-exclamation')->color('danger')
                ->visible(fn (Impersonator $r) => $r->status !== 'confirmed')
                ->requiresConfirmation()
                ->modalDescription('سيظهر الحساب في صفحة مقدم الخدمة ضمن "الحسابات المنتحلة" وفي تحذيرات البحث.')
                ->action(fn (Impersonator $r) => $r->update(['status' => 'confirmed'])),
            Tables\Actions\Action::make('reject')->label('رفض')->icon('heroicon-o-x-mark')->color('gray')
                ->visible(fn (Impersonator $r) => $r->status !== 'rejected')
                ->action(fn (Impersonator $r) => $r->update(['status' => 'rejected'])),
            Tables\Actions\ViewAction::make()->label('البلاغات'),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListImpersonators::route('/'),
        ];
    }
}
