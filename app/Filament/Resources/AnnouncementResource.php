<?php

namespace App\Filament\Resources;

use App\Filament\Forms\TranslationFields;
use App\Filament\Resources\AnnouncementResource\Pages;
use App\Models\Announcement;
use App\Models\Market;
use App\Notifications\AnnouncementNotification;
use App\Notifications\Channels\TelegramChannel;
use App\Support\AnnouncementAudience;
use App\Support\AnnouncementSender;
use App\Support\NotificationPrefs;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/** Announcements to members by email and Telegram: write, pick the audience, test, send now or schedule. */
class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'المحتوى';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'إعلان';

    protected static ?string $pluralModelLabel = 'الإعلانات';

    public static function form(Form $form): Form
    {
        $locked = fn (?Announcement $record) => $record && ! $record->isEditable();

        return $form->disabled($locked)->schema([
            Forms\Components\Section::make('الرسالة')->schema([
                TranslationFields::tabs([
                    'title' => ['label' => 'العنوان', 'required' => true, 'maxLength' => 150, 'placeholder' => 'مثال: ميزة جديدة: تنبيهات القنوات المنتحلة'],
                    'body' => ['label' => 'النص', 'textarea' => true, 'rows' => 7, 'required' => true, 'maxLength' => 2000,
                        'helper' => 'افصل الفقرات بسطر فارغ. على تيليجرام يظهر النص تحت الصورة إن كان قصيراً (أقل من 1000 حرف تقريباً).'],
                    'button_label' => ['label' => 'نص الزر', 'maxLength' => 40, 'placeholder' => 'اقرأ المزيد'],
                ]),
                Forms\Components\TextInput::make('button_url')->label('رابط الزر')->url()->maxLength(255)
                    ->placeholder('https://…')->extraInputAttributes(['dir' => 'ltr'])
                    ->helperText('اختياري. نعدّ النقرات ونضيف وسوم UTM ليظهر مصدر الزيارات في Google Analytics.'),
                Forms\Components\FileUpload::make('image')->label('صورة (اختياري)')->image()->disk('public')->directory('announcements')
                    ->maxSize(2048)->helperText('تظهر أعلى الإيميل ومع رسالة تيليجرام. يُفضّل 1200×630.'),
            ]),

            Forms\Components\Section::make('الجمهور')->columns(2)->schema([
                Forms\Components\Select::make('audience.channel')->label('القناة')->options(AnnouncementAudience::CHANNEL_MODES)
                    ->default('auto')->required()->live()->columnSpanFull(),
                Forms\Components\Select::make('audience.markets')->label('من قيّموا قنوات في هذه الأسواق')->multiple()
                    ->options(fn () => Market::active()->pluck('name', 'id'))->placeholder('كل الأسواق')->live(),
                Forms\Components\Select::make('audience.joined_days')->label('تاريخ التسجيل')->options(AnnouncementAudience::JOINED)
                    ->placeholder('كل الأعضاء')->live(),
                Forms\Components\Radio::make('audience.reviewed')->label('التقييمات')->options(AnnouncementAudience::REVIEWED)
                    ->default('any')->inline()->live()->columnSpanFull(),
                Forms\Components\Placeholder::make('estimate')->label('سيصل إلى')->columnSpanFull()
                    ->content(fn (Get $get) => self::estimateText((array) $get('audience'))),
            ]),

            Forms\Components\Section::make('الموعد')->schema([
                Forms\Components\DateTimePicker::make('scheduled_at')->label('موعد الإرسال')->native(false)->seconds(false)
                    ->minDate(now()->startOfDay())
                    ->helperText('إذا حدّدت موعداً يُرسل تلقائياً فيه. اتركه فارغاً لتحفظه مسودة وترسله بزر «إرسال الآن».'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('الإعلان')->weight('bold')->wrap()
                    ->description(fn (Announcement $r) => \Illuminate\Support\Str::limit($r->body, 80)),
                Tables\Columns\TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn ($state) => Announcement::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) { 'sent' => 'success', 'scheduled' => 'warning', 'sending' => 'info', default => 'gray' }),
                Tables\Columns\TextColumn::make('when')->label('الموعد')
                    ->getStateUsing(fn (Announcement $r) => ($r->sent_at ?? $r->scheduled_at)?->format('Y/m/d H:i'))->placeholder('—'),
                Tables\Columns\TextColumn::make('recipients_count')->label('المستلمون')->numeric(),
                Tables\Columns\TextColumn::make('sent_count')->label('وصلت')->numeric()
                    ->description(fn (Announcement $r) => $r->failed_count ? $r->failed_count.' فشلت' : null),
                Tables\Columns\TextColumn::make('clicks_count')->label('النقرات')->numeric()
                    ->description(fn (Announcement $r) => $r->sent_count && $r->button_url ? round($r->clicks_count / $r->sent_count * 100, 1).'%' : null),
            ])
            ->actions([
                Tables\Actions\Action::make('test')->label('تجربة لي')->icon('heroicon-o-paper-airplane')->color('gray')
                    ->action(fn (Announcement $record) => self::sendTest($record)),
                Tables\Actions\Action::make('send')->label('إرسال الآن')->icon('heroicon-o-megaphone')->color('success')
                    ->visible(fn (Announcement $r) => $r->isEditable())
                    ->requiresConfirmation()->modalHeading('إرسال الإعلان الآن؟')
                    ->modalDescription(fn (Announcement $r) => self::estimateText((array) $r->audience).'. لا يمكن التراجع بعد الإرسال.')
                    ->action(fn (Announcement $record) => self::sendNow($record)),
                Tables\Actions\EditAction::make()->label(fn (Announcement $r) => $r->isEditable() ? 'تعديل' : 'عرض'),
                Tables\Actions\DeleteAction::make()->label('حذف'),
            ])
            ->emptyStateHeading('لا توجد إعلانات بعد')
            ->emptyStateDescription('أرسل لأعضائك خبراً أو ميزة جديدة على البريد وتيليجرام. يصل للعضو إعلانان في الأسبوع كحد أقصى.');
    }

    public static function estimateText(array $audience): HtmlString|string
    {
        $counts = AnnouncementAudience::estimate($audience + ['channel' => 'auto']);
        if (! $counts['total']) {
            return 'لا يوجد أعضاء يطابقون هذه الشروط ويقبلون الإعلانات حالياً';
        }

        return $counts['total'].' عضو ('.$counts['telegram'].' على تيليجرام، '.$counts['mail'].' على البريد)';
    }

    /** To the admin's own email and Telegram, whatever their switches say; not counted in the stats */
    public static function sendTest(Announcement $announcement): void
    {
        $user = auth()->user();
        $channels = collect(NotificationPrefs::CHANNELS)->filter(fn ($c) => NotificationPrefs::canReach($user, $c))
            ->map(fn ($c) => $c === 'telegram' ? TelegramChannel::class : 'mail')->values()->all();

        if (! $channels) {
            Notification::make()->title('لا توجد قناة متاحة لحسابك')->body('أكّد بريدك أو اربط حسابك بتيليجرام من الموقع أولاً.')->warning()->send();

            return;
        }

        try {
            $notification = new AnnouncementNotification($announcement, $channels);
            $notification->isTest = true;
            $user->notifyNow($notification);
        } catch (\Throwable $e) {
            Notification::make()->title('تعذّر إرسال التجربة')->body(\Illuminate\Support\Str::limit($e->getMessage(), 300))->danger()->persistent()->send();

            return;
        }

        Notification::make()->title('أُرسلت التجربة إلى '.collect($channels)->map(fn ($c) => $c === 'mail' ? 'بريدك' : 'تيليجرام')->implode(' و'))->success()->send();
    }

    public static function sendNow(Announcement $announcement): void
    {
        $count = AnnouncementSender::send($announcement);

        Notification::make()
            ->title($count ? "بدأ الإرسال إلى $count عضو" : 'لم يُرسل لأحد')
            ->body($count ? 'تصل الرسائل خلال دقائق، وتظهر الأرقام في جدول الإعلانات.' : 'لا يوجد أعضاء يطابقون الجمهور المحدد.')
            ->{$count ? 'success' : 'warning'}()->send();
    }

    /** Scheduled when it has a future time, otherwise a draft */
    public static function withStatus(array $data): array
    {
        $data['status'] = filled($data['scheduled_at'] ?? null) && \Illuminate\Support\Carbon::parse($data['scheduled_at'])->isFuture() ? 'scheduled' : 'draft';
        if ($data['status'] === 'draft') {
            $data['scheduled_at'] = null;
        }

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnnouncements::route('/'),
            'create' => Pages\CreateAnnouncement::route('/create'),
            'edit' => Pages\EditAnnouncement::route('/{record}/edit'),
        ];
    }
}
