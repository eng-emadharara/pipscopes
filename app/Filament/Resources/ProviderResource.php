<?php

namespace App\Filament\Resources;

use App\Filament\Forms\TranslationFields;
use App\Filament\Resources\ProviderResource\Pages;
use App\Filament\Resources\ProviderResource\RelationManagers\ChangesRelationManager;
use App\Filament\Resources\ProviderResource\RelationManagers\ImpersonatorsRelationManager;
use App\Filament\Resources\ProviderResource\RelationManagers\OwnersRelationManager;
use App\Filament\Resources\ProviderResource\RelationManagers\ReviewsRelationManager;
use App\Models\Provider;
use App\Services\TelegramChannel;
use App\Services\TelegramRefresh;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ProviderResource extends Resource
{
    protected static ?string $model = Provider::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'المحتوى';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'مقدم خدمة';

    protected static ?string $pluralModelLabel = 'مقدمو الخدمة';

    protected static ?string $recordTitleAttribute = 'name';

    /** Translatable provider fields, shared with the owner portal's "channel details" page */
    public static function descriptionFields(): array
    {
        return [
            'service_type' => ['label' => 'نوع الخدمة', 'required' => true, 'maxLength' => 150, 'placeholder' => 'مثال: توصيات ذهب وفوركس'],
            'bio' => ['label' => 'نبذة', 'textarea' => true, 'rows' => 5, 'maxLength' => 3000],
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Group::make()->columnSpan(2)->schema([
                Forms\Components\Section::make('البيانات الأساسية')->columns(2)->schema([
                    Forms\Components\TextInput::make('name')->label('الاسم / اسم القناة')->required()->maxLength(150),
                    Forms\Components\TextInput::make('slug')->label('رابط الصفحة (slug)')
                        ->helperText('اتركه فارغاً للتوليد التلقائي')
                        ->unique(ignoreRecord: true)->maxLength(150),
                    Forms\Components\Select::make('categories')->label('التصنيفات')
                        ->helperText('يمكن اختيار أكثر من تصنيف، ويظهر مقدم الخدمة في صفحة كل تصنيف منها.')
                        ->relationship('categories', 'name')
                        ->multiple()->searchable()->preload()->required()
                        ->columnSpanFull()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')->label('الاسم')->required(),
                            Forms\Components\TextInput::make('icon')->label('أيقونة'),
                        ]),
                    Forms\Components\Select::make('pricing')->label('التسعير')->options(__('trust.pricing_options')),
                    Forms\Components\CheckboxList::make('markets')->label('الأسواق')
                        ->relationship('markets', 'name', fn ($query) => $query->where('is_active', true)->orderBy('sort_order'))
                        ->getOptionLabelFromRecordUsing(fn ($record) => trim($record->icon.' '.$record->name))
                        ->helperText('تُستخدم في فلاتر التصفح. لإضافة سوق جديد: الإعدادات ← الأسواق.')
                        ->columns(3)->gridDirection('row')->columnSpanFull(),
                ]),
                Forms\Components\Section::make('الوصف')->schema([
                    TranslationFields::tabs(static::descriptionFields()),
                ]),
                Forms\Components\Section::make('روابط التيليجرام والسوشيال ميديا')->columns(2)->schema([
                    Forms\Components\TextInput::make('telegram_url')->label('رابط قناة التيليجرام')->url()->prefixIcon('heroicon-o-paper-airplane'),
                    Forms\Components\TextInput::make('telegram_members')->label('عدد مشتركي التيليجرام')->numeric()
                        ->helperText('يُحدَّث تلقائياً مرة يومياً من تيليجرام.'),
                    Forms\Components\Placeholder::make('telegram_sync')->label('التحديث التلقائي من تيليجرام')->columnSpanFull()
                        ->visible(fn (?Provider $record) => filled($record?->telegram_url))
                        ->content(fn (Provider $record) => static::telegramSyncStatus($record)),
                    Forms\Components\TextInput::make('website_url')->label('الموقع')->url(),
                    Forms\Components\TextInput::make('twitter_url')->label('X / تويتر')->url(),
                    Forms\Components\TextInput::make('instagram_url')->label('إنستغرام')->url(),
                    Forms\Components\TextInput::make('youtube_url')->label('يوتيوب')->url(),
                    Forms\Components\TextInput::make('tiktok_url')->label('تيك توك')->url(),
                    Forms\Components\TextInput::make('facebook_url')->label('فيسبوك')->url(),
                    Forms\Components\TextInput::make('whatsapp')->label('واتساب (للإدارة فقط)'),
                    Forms\Components\Repeater::make('extra_links')->label('روابط رسمية إضافية')
                        ->helperText('مثل حساب الدعم، بوت الاشتراك، أو قناة احتياطية. تظهر ضمن "الروابط الرسمية"، ولا يمكن الإبلاغ عنها كحسابات منتحلة.')
                        ->schema([
                            Forms\Components\TextInput::make('label')->label('الوصف')->placeholder('حساب الدعم'),
                            Forms\Components\TextInput::make('url')->label('الرابط')->url()->required(),
                        ])
                        ->columns(2)->columnSpanFull()->defaultItems(0)->addActionLabel('إضافة رابط رسمي')->reorderable(),
                ]),
            ]),
            Forms\Components\Group::make()->schema([
                Forms\Components\Section::make('الصورة')->schema([
                    Forms\Components\FileUpload::make('avatar')->label('الشعار / الصورة')
                        ->image()->avatar()->imageEditor()
                        // No SVG: it can carry scripts and is served from our own domain
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/gif'])
                        ->disk('public')->directory('providers'),
                ]),
                Forms\Components\Section::make('الحالة')->schema([
                    Forms\Components\Toggle::make('is_active')->label('ظاهر على الموقع')->default(true),
                    Forms\Components\Toggle::make('is_verified')->label('موثّق'),
                    Forms\Components\Toggle::make('is_featured')->label('مميز في الرئيسية'),
                ]),
                Forms\Components\Section::make('الإحصائيات')->hiddenOn('create')->schema([
                    Forms\Components\Placeholder::make('rating_avg')->label('التقييم الحالي')
                        ->content(fn (?Provider $record) => $record ? number_format($record->rating_avg, 2).' / 5' : '-'),
                    Forms\Components\Placeholder::make('reviews_count')->label('عدد التقييمات المنشورة')
                        ->content(fn (?Provider $record) => $record?->reviews_count ?? 0),
                ]),
            ]),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')->label('')->disk('public')->circular()
                    ->defaultImageUrl(fn (Provider $record) => 'https://ui-avatars.com/api/?background=d1fae5&color=047857&name='.urlencode($record->name)),
                Tables\Columns\TextColumn::make('name')->label('الاسم')->searchable()->sortable()->wrap()
                    ->description(fn (Provider $record) => $record->service_type),
                Tables\Columns\TextColumn::make('categories.name')->label('التصنيفات')->badge()->wrap(),
                Tables\Columns\TextColumn::make('markets.name')->label('الأسواق')->badge()->color('gray')->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('rating_avg')->label('التقييم')->sortable()
                    ->formatStateUsing(fn ($state) => number_format($state, 1).' ★')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state >= 4 => 'success',
                        $state >= 3 => 'warning',
                        $state > 0 => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('reviews_count')->label('التقييمات')->sortable(),
                Tables\Columns\IconColumn::make('telegram_url')->label('تيليجرام')
                    ->icon(fn ($state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-minus')
                    ->color(fn ($state) => $state ? 'info' : 'gray')
                    ->url(fn (Provider $record) => $record->telegram_url, shouldOpenInNewTab: true),
                Tables\Columns\TextColumn::make('owners_count')->counts('owners')->label('حساب صاحب القناة')
                    ->formatStateUsing(fn ($state) => $state ? 'لديه حساب' : 'إضافة حساب')
                    ->badge()->color(fn ($state) => $state ? 'success' : 'gray')
                    ->icon('heroicon-o-key')
                    ->url(fn (Provider $record) => static::ownersTabUrl($record)),
                Tables\Columns\ToggleColumn::make('is_verified')->label('موثّق'),
                Tables\Columns\ToggleColumn::make('is_active')->label('ظاهر'),
                Tables\Columns\TextColumn::make('created_at')->label('أضيف')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('categories')->label('التصنيف')->relationship('categories', 'name')->multiple()->preload(),
                Tables\Filters\SelectFilter::make('markets')->label('السوق')->relationship('markets', 'name')->multiple()->preload(),
                Tables\Filters\TernaryFilter::make('is_verified')->label('موثّق'),
                Tables\Filters\TernaryFilter::make('is_active')->label('ظاهر'),
                Tables\Filters\TernaryFilter::make('is_featured')->label('مميز'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')->label('عرض')->icon('heroicon-o-eye')
                    ->url(fn (Provider $record) => route('providers.show', $record), shouldOpenInNewTab: true),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** When the channel was last read from Telegram, what came back, and its latest numbers. */
    public static function telegramSyncStatus(Provider $record): HtmlString
    {
        if (! $record->telegram_checked_at) {
            return new HtmlString('<span style="color:#6b7280">لم تُقرأ بعد. ستُقرأ في التحديث القادم، أو اضغط "تحديث من تيليجرام" أعلى الصفحة.</span>');
        }

        $outcome = [
            TelegramChannel::CHANNEL => '<span style="color:#059669">ناجح</span>',
            TelegramChannel::PRIVATE => '<span style="color:#dc2626">القناة أصبحت خاصة</span>',
            TelegramChannel::NOT_FOUND => '<span style="color:#dc2626">القناة غير موجودة (حُذفت أو تغيّر اسمها)</span>',
            TelegramChannel::NOT_CHANNEL => '<span style="color:#dc2626">الرابط لم يعد لقناة</span>',
            TelegramChannel::UNREACHABLE => '<span style="color:#d97706">تعذّر الوصول لتيليجرام</span>',
        ][$record->telegram_status] ?? e($record->telegram_status);

        $d = $record->telegram_data ?? [];
        $parts = array_filter([
            'آخر قراءة: '.e($record->telegram_checked_at->diffForHumans()).' — '.$outcome,
            $record->telegram_failures > 1 ? 'فشلت '.$record->telegram_failures.' مرات متتالية'
                .($record->telegram_failures >= TelegramRefresh::GIVE_UP_AFTER ? '، لذلك تُعاد المحاولة أسبوعياً فقط' : '') : null,
            isset($d['avg_views']) ? 'متوسط مشاهدات آخر المنشورات: '.number_format($d['avg_views']) : null,
            isset($d['last_post_at']) ? 'آخر منشور: '.e(\Illuminate\Support\Carbon::parse($d['last_post_at'])->diffForHumans()) : null,
            'سجل الأرقام: '.$record->telegramSnapshots()->count().' نقطة',
        ]);

        return new HtmlString(implode('<br>', $parts));
    }

    public static function getRelations(): array
    {
        return [
            ReviewsRelationManager::class,
            ImpersonatorsRelationManager::class,
            OwnersRelationManager::class,
            ChangesRelationManager::class,
        ];
    }

    /** Edit page opened on the "owner accounts" tab (tabs are keyed by their position in getRelations()). */
    public static function ownersTabUrl(Provider $provider): string
    {
        $tab = array_search(OwnersRelationManager::class, static::getRelations(), true);

        return static::getUrl('edit', ['record' => $provider, 'activeRelationManager' => $tab]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'service_type', 'telegram_url'];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProviders::route('/'),
            'create' => Pages\CreateProvider::route('/create'),
            'edit' => Pages\EditProvider::route('/{record}/edit'),
        ];
    }
}
