<?php

namespace App\Filament\Pages;

use App\Models\NotificationLog;
use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\User;
use App\Notifications\TestNotification;
use App\Support\NotificationPrefs;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/** Site-wide switches for member notifications (App\Support\NotificationPrefs), and the log of what was sent. */
class NotificationSettings extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-bell';

    protected static ?string $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'الإشعارات';

    protected static ?string $title = 'إعدادات الإشعارات';

    protected static ?string $slug = 'notifications';

    protected static string $view = 'filament.pages.owner-portal-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'types' => collect(NotificationPrefs::TYPES)->mapWithKeys(fn ($meta, $type) => [$type => NotificationPrefs::siteEnabled("types.$type")])->all(),
            'channels' => collect(NotificationPrefs::CHANNELS)->mapWithKeys(fn ($channel) => [$channel => NotificationPrefs::siteEnabled("channels.$channel")])->all(),
            'announcements_default' => NotificationPrefs::siteEnabled('announcements_default'),
            'weekly_cap' => NotificationPrefs::weeklyCap(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test')->label('أرسل لي رسالة تجريبية')->icon('heroicon-o-paper-airplane')->color('gray')
                ->requiresConfirmation()
                ->modalDescription('تصلك على بريدك المؤكد وعلى تيليجرام إن كان حسابك مربوطاً به، حتى لو كانت الإشعارات مطفأة.')
                ->action(function () {
                    $user = auth()->user();
                    $via = (new TestNotification)->via($user);
                    if (! $via) {
                        Notification::make()->title('لا توجد قناة متاحة لحسابك')
                            ->body('أكّد بريدك أو اربط حسابك بتيليجرام من الموقع أولاً.')->warning()->send();

                        return;
                    }

                    $before = NotificationLog::max('id') ?? 0;
                    try {
                        $user->notifyNow(new TestNotification);
                    } catch (\Throwable $e) {
                        Notification::make()->title('تعذّر الإرسال')->body(\Illuminate\Support\Str::limit($e->getMessage(), 300))->danger()->persistent()->send();

                        return;
                    }
                    $logs = NotificationLog::where('id', '>', $before)->get();
                    $failed = $logs->where('status', 'failed');

                    Notification::make()
                        ->title($failed->isEmpty() ? 'تم الإرسال' : 'تعذّر الإرسال على بعض القنوات')
                        ->body($logs->map(fn ($l) => ($l->channel === 'mail' ? 'البريد' : 'تيليجرام').': '.($l->status === 'sent' ? 'أُرسلت ✓' : 'فشلت - '.$l->error))->implode("\n"))
                        ->{$failed->isEmpty() ? 'success' : 'danger'}()->persistent()->send();
                }),
        ];
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Forms\Components\Section::make('أنواع الإشعارات')
                ->description('يختار كل عضو أيضاً ما يصله من صفحة «إعدادات الإشعارات» في حسابه. المطفأ هنا لا يُرسل لأحد ويختفي من صفحة العضو.')
                ->schema(collect(NotificationPrefs::TYPES)->map(fn ($meta, $type) => Forms\Components\Toggle::make("types.$type")
                    ->label($meta[0])->helperText($meta[1]))->values()->all()),

            Forms\Components\Section::make('القنوات')->columns(2)->schema([
                Forms\Components\Toggle::make('channels.mail')->label('البريد الإلكتروني')
                    ->helperText(fn () => User::whereNotNull('email_verified_at')->count().' عضو ببريد مؤكد.'),
                Forms\Components\Toggle::make('channels.telegram')->label('تيليجرام (رسائل من بوت تسجيل الدخول)')
                    ->helperText(fn () => SocialAccount::where('provider', 'telegram')
                        ->whereHas('user', fn ($q) => $q->whereNull('telegram_blocked_at'))->count()
                        .' عضو مربوط بتيليجرام. '.(filled(config('services.telegram.bot_token')) ? '' : '⚠️ لم يُضبط البوت بعد في «تسجيل الدخول الاجتماعي».')),
            ]),

            Forms\Components\Section::make('الإعلانات')->columns(2)
                ->description('تُكتب وتُرسل من: المحتوى ← الإعلانات.')
                ->schema([
                    Forms\Components\Toggle::make('announcements_default')->label('مفعّلة للأعضاء الذين لم يختاروا بعد')
                        ->helperText('عند الإطفاء لا تصل الإعلانات إلا لمن فعّلها بنفسه من صفحة إعدادات الإشعارات.'),
                    Forms\Components\TextInput::make('weekly_cap')->label('أقصى عدد إعلانات للعضو في 7 أيام')
                        ->numeric()->minValue(0)->maxValue(14)->required()
                        ->helperText('0 = بلا حد. الإكثار يدفع الأعضاء لحظر البوت أو اعتبار الإيميل سبام.'),
                ]),

            Forms\Components\Placeholder::make('queue')->label('')
                ->content('تُرسل الرسائل في الخلفية عبر cron الموقع (php artisan schedule:run كل دقيقة)، فقد تتأخر دقيقة أو اثنتين.'),
        ]);
    }

    public function save(): void
    {
        Setting::put([NotificationPrefs::SETTING => $this->form->getState()]);

        Notification::make()->title('تم حفظ إعدادات الإشعارات')->success()->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('سجل الإشعارات')
            ->query(NotificationLog::query()->with('user')->latest('id'))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('التاريخ')->since()->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('العضو')->placeholder('حساب محذوف')->searchable(),
                Tables\Columns\TextColumn::make('subject')->label('الرسالة')->wrap()->limit(70),
                Tables\Columns\TextColumn::make('channel')->label('القناة')->badge()
                    ->formatStateUsing(fn ($state) => $state === 'mail' ? 'البريد' : 'تيليجرام')
                    ->color(fn ($state) => $state === 'mail' ? 'gray' : 'info'),
                Tables\Columns\TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn ($state) => $state === 'sent' ? 'أُرسلت' : 'فشلت')
                    ->color(fn ($state) => $state === 'sent' ? 'success' : 'danger')
                    ->tooltip(fn (NotificationLog $r) => $r->error),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('الحالة')->options(['sent' => 'أُرسلت', 'failed' => 'فشلت']),
                Tables\Filters\SelectFilter::make('channel')->label('القناة')->options(['mail' => 'البريد', 'telegram' => 'تيليجرام']),
                Tables\Filters\SelectFilter::make('type')->label('النوع')->options(collect(NotificationPrefs::TYPES)->map(fn ($m) => $m[0])->all()),
            ])
            ->paginated([25, 50]);
    }
}
