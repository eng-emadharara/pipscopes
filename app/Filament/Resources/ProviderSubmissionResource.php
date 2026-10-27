<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProviderSubmissionResource\Pages;
use App\Models\Category;
use App\Models\Market;
use App\Models\ProviderSubmission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ProviderSubmissionResource extends Resource
{
    protected static ?string $model = ProviderSubmission::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'الطلبات والتقييمات';

    protected static ?string $modelLabel = 'طلب إضافة';

    protected static ?string $pluralModelLabel = 'طلبات الإضافة';

    public static function getNavigationBadge(): ?string
    {
        $new = ProviderSubmission::where('status', 'new')->count();

        return $new ?: null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            // The form no longer asks who sends a channel; only older requests have a sender
            Forms\Components\Section::make('مرسل الطلب')->columns(3)
                ->visible(fn (?ProviderSubmission $record) => filled($record?->submitter_name))
                ->schema([
                    Forms\Components\TextInput::make('submitter_name')->label('الاسم'),
                    Forms\Components\TextInput::make('submitter_contact')->label('وسيلة التواصل'),
                    Forms\Components\Select::make('relation')->label('العلاقة')->options(ProviderSubmission::RELATIONS),
                ]),
            Forms\Components\Section::make('بيانات القناة من تيليجرام')
                ->description('قُرئت من صفحة القناة العامة لحظة الإرسال.')
                ->visible(fn (?ProviderSubmission $record) => filled($record?->telegram_data))
                ->schema([
                    Forms\Components\Placeholder::make('telegram_summary')->hiddenLabel()
                        ->content(fn (ProviderSubmission $record) => static::telegramSummary($record)),
                ]),
            Forms\Components\Section::make('بيانات مقدم الخدمة')->columns(2)->schema([
                Forms\Components\TextInput::make('name')->label('الاسم')->required(),
                Forms\Components\Select::make('category_ids')->label('التصنيفات')
                    ->options(fn () => Category::orderBy('sort_order')->pluck('name', 'id'))
                    ->multiple()->required(),
                Forms\Components\TextInput::make('service_type')->label('نوع الخدمة')->required(),
                Forms\Components\Select::make('market_ids')->label('الأسواق')
                    ->options(fn () => Market::orderBy('sort_order')->pluck('name', 'id'))
                    ->multiple(),
                Forms\Components\Select::make('pricing')->label('التسعير')->options(__('trust.pricing_options')),
                Forms\Components\Textarea::make('description')->label('النبذة')->columnSpanFull(),
            ]),
            Forms\Components\Section::make('الروابط')->columns(2)->schema(
                // config('trust.socials') maps field => icon; labels live in lang/ar/trust.php (admin is Arabic)
                collect(array_keys(config('trust.socials')))
                    ->map(fn (string $field) => Forms\Components\TextInput::make($field)->label(trans("trust.socials.$field", [], 'ar'))->url()
                        ->suffixAction(fn ($state) => $state ? Forms\Components\Actions\Action::make('open_'.$field)
                            ->icon('heroicon-o-arrow-top-right-on-square')->url($state, shouldOpenInNewTab: true) : null))
                    ->values()->all()
            ),
            Forms\Components\Section::make('المراجعة')->columns(2)->schema([
                Forms\Components\Select::make('status')->label('الحالة')->options(ProviderSubmission::STATUSES)->required(),
                Forms\Components\Select::make('provider_id')->label('مرتبط بمقدم الخدمة')->relationship('provider', 'name')->searchable(),
                Forms\Components\Textarea::make('admin_notes')->label('ملاحظات إدارية')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')->label('')->circular()->size(40)
                    ->getStateUsing(fn (ProviderSubmission $r) => $r->avatarUrl()),
                Tables\Columns\TextColumn::make('name')->label('القناة')->searchable()
                    ->description(fn (ProviderSubmission $r) => $r->service_type),
                Tables\Columns\TextColumn::make('telegram_data.subscribers')->label('المشتركون')->numeric()->placeholder('—'),
                Tables\Columns\TextColumn::make('category_ids')->label('التصنيفات')->badge()->wrap()
                    ->formatStateUsing(fn ($state) => Category::find($state)?->name),
                Tables\Columns\TextColumn::make('telegram_url')->label('تيليجرام')->limit(30)
                    ->url(fn (ProviderSubmission $r) => $r->telegram_url, shouldOpenInNewTab: true)->color('info'),
                Tables\Columns\TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn ($state) => ProviderSubmission::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'new' => 'warning',
                        'approved' => 'success',
                        default => 'danger',
                    }),
                Tables\Columns\TextColumn::make('created_at')->label('التاريخ')->since()->sortable(),
            ])
            ->actions([
                static::convertAction(Tables\Actions\Action::class),
                Tables\Actions\Action::make('reject')->label('رفض')->icon('heroicon-o-x-mark')->color('danger')
                    ->visible(fn (ProviderSubmission $r) => $r->status === 'new')
                    ->requiresConfirmation()
                    ->action(fn (ProviderSubmission $r) => $r->update(['status' => 'rejected'])),
                Tables\Actions\EditAction::make()->label('مراجعة'),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    /**
     * "Add as provider" action, shared by the table row and the edit page header.
     *
     * @param  class-string<\Filament\Tables\Actions\Action|\Filament\Actions\Action>  $actionClass
     */
    public static function convertAction(string $actionClass)
    {
        return $actionClass::make('convert')
            ->label('إضافة كمقدم خدمة')
            ->icon('heroicon-o-user-plus')
            ->color('success')
            ->visible(fn (ProviderSubmission $record) => ! $record->provider_id)
            ->requiresConfirmation()
            ->modalDescription('سيتم إنشاء صفحة مقدم خدمة جديدة من بيانات هذا الطلب، ويمكنك تعديلها بعد ذلك.')
            ->action(function (ProviderSubmission $record) {
                $provider = $record->convertToProvider();

                Notification::make()->title('تمت إضافة مقدم الخدمة')->success()->send();

                return redirect(ProviderResource::getUrl('edit', ['record' => $provider]));
            });
    }

    /** Photo, subscribers and recent activity read from Telegram, for the review screen. */
    public static function telegramSummary(ProviderSubmission $record): HtmlString
    {
        $d = $record->telegram_data ?? [];
        $rows = array_filter([
            'المعرّف' => isset($d['username']) ? '<span dir="ltr">@'.e($d['username']).'</span>' : null,
            'المشتركون' => isset($d['subscribers']) ? number_format($d['subscribers']) : null,
            'موثّقة من تيليجرام' => ($d['verified'] ?? false) ? 'نعم' : null,
            'آخر منشور' => isset($d['last_post_at']) ? e(\Illuminate\Support\Carbon::parse($d['last_post_at'])->diffForHumans()) : null,
            'متوسط المشاهدات (آخر المنشورات)' => isset($d['avg_views']) ? number_format($d['avg_views']) : null,
            'صور / فيديو / روابط' => isset($d['photos']) || isset($d['videos']) || isset($d['links'])
                ? ($d['photos'] ?? 0).' / '.($d['videos'] ?? 0).' / '.($d['links'] ?? 0) : null,
            'معاينة المنشورات' => ($d['preview_enabled'] ?? null) === false ? 'مغلقة من صاحب القناة' : null,
        ]);

        $photo = $record->avatarUrl()
            ? '<img src="'.e($record->avatarUrl()).'" alt="" style="width:64px;height:64px;border-radius:9999px;object-fit:cover">'
            : '';
        $list = collect($rows)->map(fn ($v, $k) => '<div style="display:flex;gap:.5rem"><dt style="color:#6b7280;min-width:12rem">'.$k.'</dt><dd>'.$v.'</dd></div>')->implode('');

        return new HtmlString('<div style="display:flex;gap:1rem;align-items:flex-start">'.$photo.'<dl style="display:grid;gap:.25rem">'.$list.'</dl></div>');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProviderSubmissions::route('/'),
            'edit' => Pages\EditProviderSubmission::route('/{record}/edit'),
        ];
    }
}
