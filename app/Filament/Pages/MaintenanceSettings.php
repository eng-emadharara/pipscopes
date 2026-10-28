<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\Maintenance;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/** Switches maintenance mode on/off and edits the maintenance page (read through App\Support\Maintenance). */
class MaintenanceSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = -1;

    protected static ?string $navigationLabel = 'وضع الصيانة';

    protected static ?string $title = 'وضع الصيانة';

    protected static ?string $slug = 'maintenance';

    protected static string $view = 'filament.pages.maintenance-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function getNavigationBadge(): ?string
    {
        return Maintenance::isActive() ? 'مفعّل' : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function mount(): void
    {
        $this->form->fill([
            'maintenance_ends_at' => Setting::get('maintenance_ends_at'),
            'maintenance_timezone' => Setting::get('maintenance_timezone', 'Asia/Amman'),
            'maintenance_show_countdown' => Setting::get('maintenance_show_countdown', true),
            'maintenance_auto_end' => Setting::get('maintenance_auto_end', false),
            'maintenance_allowed_ips' => Maintenance::allowedIps(),
            'maintenance_texts' => Setting::get('maintenance_texts', []),
            'maintenance_show_contact' => Setting::get('maintenance_show_contact', true),
            'maintenance_show_socials' => Setting::get('maintenance_show_socials', true),
            'bypass_url' => Maintenance::bypassUrl(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('enable')->label('تفعيل وضع الصيانة الآن')
                ->icon('heroicon-o-wrench-screwdriver')->color('danger')->size('lg')
                ->visible(fn () => ! Maintenance::isActive())
                ->requiresConfirmation()
                ->modalIcon('heroicon-o-wrench-screwdriver')
                ->modalHeading('تفعيل وضع الصيانة؟')
                ->modalDescription('سيرى كل الزوار صفحة الصيانة فوراً بدلاً من الموقع. أنت (كمدير) وأصحاب رابط التجاوز والعناوين المسموحة ستبقون ترون الموقع كاملاً. لوحة التحكم لا تتأثر.')
                ->modalSubmitActionLabel('نعم، فعّل الصيانة')
                ->action(fn () => $this->switch(true)),

            Action::make('disable')->label('إيقاف وضع الصيانة وفتح الموقع')
                ->icon('heroicon-o-check-circle')->color('success')->size('lg')
                ->visible(fn () => Maintenance::isActive())
                ->requiresConfirmation()
                ->modalIcon('heroicon-o-check-circle')
                ->modalIconColor('success')
                ->modalHeading('إيقاف وضع الصيانة؟')
                ->modalDescription('سيعود الموقع متاحاً لجميع الزوار فوراً، وصفحات الصيانة المفتوحة عندهم ستعود للموقع تلقائياً خلال ثوانٍ.')
                ->modalSubmitActionLabel('نعم، افتح الموقع')
                ->action(fn () => $this->switch(false)),

            Action::make('preview')->label('معاينة الصفحة')
                ->icon('heroicon-o-eye')->color('gray')
                ->url(fn () => route('home', ['preview' => 'maintenance']), shouldOpenInNewTab: true),
        ];
    }

    protected function switch(bool $enabled): void
    {
        $values = [
            'maintenance_enabled' => $enabled,
            'maintenance_started_at' => $enabled ? now()->toIso8601String() : null,
        ];

        if ($enabled) {
            // A link is ready to share with the team from the first minute
            if (! Maintenance::secret()) {
                $values['maintenance_secret'] = Str::random(40);
            }
            // An end time already past would switch it straight off again
            if (Maintenance::endsAutomatically() && Maintenance::endsAt()?->isPast()) {
                $values['maintenance_ends_at'] = null;
            }
        }

        Setting::put($values);

        Notification::make()
            ->title($enabled ? 'تم تفعيل وضع الصيانة' : 'تم إيقاف وضع الصيانة')
            ->body($enabled ? 'الزوار يرون صفحة الصيانة الآن.' : 'الموقع مفتوح للجميع الآن.')
            ->status($enabled ? 'warning' : 'success')
            ->send();

        // Reload so the menu badge and the panel banner follow the new state
        $this->redirect(static::getUrl());
    }

    public function form(Form $form): Form
    {
        $textTabs = collect(config('trust.locales'))->map(fn (array $meta, string $code) => Forms\Components\Tabs\Tab::make($code)
            ->label($meta['name'])
            ->schema([
                Forms\Components\TextInput::make("maintenance_texts.$code.headline")->label('العنوان الرئيسي')
                    ->placeholder(trans('نعمل على تحسين المنصة من أجلك', [], $code))
                    ->maxLength(120)
                    ->extraInputAttributes(['dir' => $meta['dir']]),
                Forms\Components\Textarea::make("maintenance_texts.$code.message")->label('الرسالة')->rows(3)
                    ->placeholder(trans('نجري حالياً أعمال صيانة وتحديثات مجدولة لنقدّم لك تجربة أسرع وأكثر أماناً. سنعود خلال وقت قصير، شكراً لصبرك.', [], $code))
                    ->maxLength(500)
                    ->extraInputAttributes(['dir' => $meta['dir']]),
            ]))->values()->all();

        return $form->statePath('data')->schema([
            Forms\Components\Section::make('الحالة الحالية')->schema([
                Forms\Components\Placeholder::make('status')->hiddenLabel()
                    ->content(fn () => new HtmlString($this->statusText())),
            ]),

            Forms\Components\Section::make('موعد العودة والعداد')
                ->description('اختياري. اتركه فارغاً لعرض صفحة الصيانة بدون عداد.')
                ->columns(2)
                ->schema([
                    Forms\Components\DateTimePicker::make('maintenance_ends_at')->label('الموعد المتوقع لانتهاء الصيانة')
                        ->seconds(false)
                        ->live(),
                    Forms\Components\Select::make('maintenance_timezone')->label('بتوقيت')
                        ->options(LaunchSettings::TIMEZONES)
                        ->required(fn (Forms\Get $get) => filled($get('maintenance_ends_at')))
                        ->helperText('الزوار يرون العداد صحيحاً بحسب توقيت أجهزتهم.'),
                    Forms\Components\Toggle::make('maintenance_show_countdown')->label('إظهار العداد في صفحة الصيانة')->default(true),
                    Forms\Components\Toggle::make('maintenance_auto_end')->label('فتح الموقع تلقائياً عند انتهاء الموعد')
                        ->helperText('إذا لم تفعّله، تبقى صفحة الصيانة ظاهرة بعد انتهاء الموعد حتى توقفها بنفسك.'),
                ]),

            Forms\Components\Section::make('الوصول أثناء الصيانة')
                ->description('المدراء المسجلون يرون الموقع دائماً. لمنح الوصول لغيرهم (المطورين أو فريق المراجعة) استخدم رابط التجاوز أو عناوين IP.')
                ->schema([
                    Forms\Components\TextInput::make('bypass_url')->label('رابط التجاوز الخاص')
                        ->readOnly()
                        ->dehydrated(false)
                        ->placeholder('يُنشأ تلقائياً عند تفعيل الصيانة، أو اضغط "رابط جديد"')
                        ->extraInputAttributes(['dir' => 'ltr'])
                        ->helperText('من يفتح هذا الرابط يتصفح الموقع بشكل طبيعي لمدة 30 يوماً من نفس المتصفح. إنشاء رابط جديد يلغي الرابط القديم فوراً.')
                        ->suffixAction(Forms\Components\Actions\Action::make('copy')->icon('heroicon-o-clipboard-document')->label('نسخ')
                            ->visible(fn (Forms\Get $get) => filled($get('bypass_url')))
                            ->alpineClickHandler("window.navigator.clipboard.writeText(\$wire.data.bypass_url); \$tooltip('تم النسخ', { theme: \$store.theme, timeout: 2000 })"))
                        ->hintAction(Forms\Components\Actions\Action::make('regenerate')->label('رابط جديد')->icon('heroicon-o-arrow-path')
                            ->requiresConfirmation()
                            ->modalHeading('إنشاء رابط تجاوز جديد؟')
                            ->modalDescription('الرابط الحالي سيتوقف عن العمل، ومن دخل به سيرى صفحة الصيانة مثل باقي الزوار.')
                            ->action(function (Forms\Set $set) {
                                Setting::put(['maintenance_secret' => Str::random(40)]);
                                $set('bypass_url', Maintenance::bypassUrl());
                                Notification::make()->title('تم إنشاء رابط تجاوز جديد')->success()->send();
                            })),

                    Forms\Components\TagsInput::make('maintenance_allowed_ips')->label('عناوين IP المسموح لها')
                        ->placeholder('مثال: 203.0.113.7 أو 10.0.0.0/24')
                        ->nestedRecursiveRules(['regex:/^[0-9a-fA-F:.]+(\/\d{1,3})?$/'])
                        ->helperText('اكتب العنوان ثم اضغط Enter. يقبل عناوين مفردة أو نطاقات CIDR.')
                        ->hint('عنوانك الحالي: '.request()->ip())
                        ->hintAction(Forms\Components\Actions\Action::make('addMyIp')->label('أضف عنواني')->icon('heroicon-o-plus')
                            ->action(fn (Forms\Get $get, Forms\Set $set) => $set('maintenance_allowed_ips',
                                array_values(array_unique([...(array) $get('maintenance_allowed_ips'), request()->ip()]))))),
                ]),

            Forms\Components\Section::make('محتوى صفحة الصيانة')
                ->description('أي حقل فارغ يستخدم النص الافتراضي الظاهر بداخله.')
                ->collapsible()
                ->schema([
                    Forms\Components\Tabs::make('texts')->tabs($textTabs),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Toggle::make('maintenance_show_contact')->label('إظهار بريد التواصل')
                            ->helperText('البريد من إعدادات الموقع.'),
                        Forms\Components\Toggle::make('maintenance_show_socials')->label('إظهار حسابات التواصل الاجتماعي')
                            ->helperText('الحسابات من إعدادات الموقع.'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::put([
            'maintenance_ends_at' => $data['maintenance_ends_at'] ?: null,
            'maintenance_timezone' => $data['maintenance_timezone'] ?: null,
            'maintenance_show_countdown' => (bool) $data['maintenance_show_countdown'],
            'maintenance_auto_end' => (bool) $data['maintenance_auto_end'],
            'maintenance_allowed_ips' => array_values(array_unique($data['maintenance_allowed_ips'] ?? [])),
            'maintenance_texts' => $data['maintenance_texts'] ?? [],
            'maintenance_show_contact' => (bool) $data['maintenance_show_contact'],
            'maintenance_show_socials' => (bool) $data['maintenance_show_socials'],
        ]);

        Notification::make()->title('تم حفظ إعدادات الصيانة')->body(strip_tags($this->statusText()))->success()->send();
    }

    protected function statusText(): string
    {
        $endsAt = Maintenance::endsAt();
        $local = $endsAt?->copy()->setTimezone(Setting::get('maintenance_timezone') ?: 'UTC');

        if (! Maintenance::isActive()) {
            $text = '🟢 <strong>الموقع يعمل بشكل طبيعي ومتاح للجميع.</strong>';
            if (Setting::get('maintenance_enabled') && $endsAt?->isPast()) {
                $text .= ' (انتهى موعد الصيانة وفُتح الموقع تلقائياً)';
            }

            return $text.'<br>اضغط "تفعيل وضع الصيانة الآن" في الأعلى عندما تحتاج لإغلاق الموقع مؤقتاً.';
        }

        $text = '🔴 <strong>وضع الصيانة مفعّل:</strong> الزوار يرون صفحة الصيانة (رمز 503 حتى لا تتأثر نتائج البحث).';

        if ($startedAt = Maintenance::startedAt()) {
            $text .= '<br>بدأت منذ '.e($startedAt->locale('ar')->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE, parts: 2)).'.';
        }

        if ($endsAt?->isFuture()) {
            $text .= '<br>الموعد المتوقع للعودة: <strong dir="ltr">'.e($local->format('Y-m-d H:i')).'</strong> ('.e(LaunchSettings::TIMEZONES[Setting::get('maintenance_timezone')] ?? 'UTC').')'
                .' — بعد '.e($endsAt->locale('ar')->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE, parts: 3)).'.'
                .(Maintenance::endsAutomatically() ? ' سيُفتح الموقع تلقائياً عند الموعد.' : ' لن يُفتح تلقائياً.');
        } elseif ($endsAt) {
            $text .= '<br>انتهى الموعد المتوقع، والموقع لا يزال مغلقاً لأن الفتح التلقائي غير مفعّل.';
        }

        return $text;
    }
}
