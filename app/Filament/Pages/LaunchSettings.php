<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\Launch;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;

class LaunchSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-rocket-launch';

    protected static ?string $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = -1;

    protected static ?string $navigationLabel = 'إعدادات الإطلاق';

    protected static ?string $title = 'إعدادات الإطلاق';

    protected static ?string $slug = 'launch-settings';

    protected static string $view = 'filament.pages.launch-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** Time zones offered for the launch time (value => label) */
    protected const TIMEZONES = [
        'Asia/Amman' => 'الأردن (عمّان)',
        'Asia/Hebron' => 'فلسطين (الخليل / غزة)',
        'Asia/Riyadh' => 'السعودية (الرياض)',
        'Asia/Dubai' => 'الإمارات (دبي)',
        'Asia/Kuwait' => 'الكويت',
        'Asia/Qatar' => 'قطر',
        'Asia/Baghdad' => 'العراق (بغداد)',
        'Asia/Damascus' => 'سوريا (دمشق)',
        'Asia/Beirut' => 'لبنان (بيروت)',
        'Africa/Cairo' => 'مصر (القاهرة)',
        'Africa/Casablanca' => 'المغرب (الدار البيضاء)',
        'Europe/Istanbul' => 'تركيا (إسطنبول)',
        'UTC' => 'UTC',
    ];

    public static function getNavigationBadge(): ?string
    {
        return Launch::isPrelaunch() ? 'قبل الإطلاق' : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function mount(): void
    {
        $this->form->fill([
            'prelaunch_enabled' => Setting::get('prelaunch_enabled', false),
            'launch_at' => Setting::get('launch_at'),
            'launch_timezone' => Setting::get('launch_timezone', 'Asia/Amman'),
            'show_countdown' => Setting::get('show_countdown', true),
            'auto_launch' => Setting::get('auto_launch', false),
            'prelaunch_texts' => Setting::get('prelaunch_texts', []),
        ]);
    }

    public function form(Form $form): Form
    {
        $textTabs = collect(config('trust.locales'))->map(fn (array $meta, string $code) => Forms\Components\Tabs\Tab::make($code)
            ->label($meta['name'])
            ->schema([
                Forms\Components\TextInput::make("prelaunch_texts.$code.headline")->label('العنوان الرئيسي')
                    ->placeholder(trans('قريباً: منصة مستقلة لتقييم قنوات التوصيات ومقدمي الخدمات', [], $code))
                    ->extraInputAttributes(['dir' => $meta['dir']]),
                Forms\Components\Textarea::make("prelaunch_texts.$code.subheadline")->label('النص التعريفي')->rows(3)
                    ->placeholder(trans('نجهّز الآن قائمة قنوات التوصيات وخدمات نسخ الصفقات قبل الإطلاق. هل تدير قناة أو تعرف قناة تستحق التقييم؟ أضفها الآن لتكون جاهزة للتقييم يوم الإطلاق.', [], $code))
                    ->extraInputAttributes(['dir' => $meta['dir']]),
            ]))->values()->all();

        return $form->statePath('data')->schema([
            Forms\Components\Section::make('الحالة الحالية')->schema([
                Forms\Components\Placeholder::make('status')->label('الحالة')->hiddenLabel()
                    ->content(fn () => new HtmlString($this->statusText())),
            ]),

            Forms\Components\Section::make('وضع ما قبل الإطلاق')->schema([
                Forms\Components\Toggle::make('prelaunch_enabled')->label('تفعيل وضع ما قبل الإطلاق')
                    ->helperText('عند التفعيل يرى الزوار صفحة الإطلاق ونموذج إضافة مقدم خدمة فقط (مع صفحات من نحن وتواصل معنا والخصوصية). التصفح والتقييمات والتسجيل تكون مغلقة. المدراء يرون الموقع كاملاً دائماً.')
                    ->live(),
            ]),

            Forms\Components\Section::make('العداد التنازلي')
                ->description('اختياري. اتركه فارغاً لعرض صفحة الإطلاق بدون عداد.')
                ->columns(2)
                ->schema([
                    Forms\Components\DateTimePicker::make('launch_at')->label('موعد الإطلاق')
                        ->seconds(false)
                        ->live(),
                    Forms\Components\Select::make('launch_timezone')->label('بتوقيت')
                        ->options(self::TIMEZONES)
                        ->required(fn (Forms\Get $get) => filled($get('launch_at')))
                        ->helperText('الزوار يرون العداد صحيحاً بحسب توقيت أجهزتهم.')
                        ->live(),
                    Forms\Components\Toggle::make('show_countdown')->label('إظهار العداد في صفحة الإطلاق')->default(true),
                    Forms\Components\Toggle::make('auto_launch')->label('فتح الموقع تلقائياً عند انتهاء العداد')
                        ->helperText('إذا لم تفعّله، تبقى صفحة الإطلاق ظاهرة بعد انتهاء العداد حتى تطفئ وضع ما قبل الإطلاق بنفسك.'),
                ]),

            Forms\Components\Section::make('نصوص صفحة الإطلاق')
                ->description('اختياري. أي حقل فارغ يستخدم النص الافتراضي الظاهر بداخله.')
                ->collapsible()
                ->schema([Forms\Components\Tabs::make('texts')->tabs($textTabs)]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::put([
            'prelaunch_enabled' => (bool) $data['prelaunch_enabled'],
            'launch_at' => $data['launch_at'] ?: null,
            'launch_timezone' => $data['launch_timezone'] ?: null,
            'show_countdown' => (bool) $data['show_countdown'],
            'auto_launch' => (bool) $data['auto_launch'],
            'prelaunch_texts' => $data['prelaunch_texts'] ?? [],
        ]);

        Notification::make()->title('تم حفظ إعدادات الإطلاق')->body(strip_tags($this->statusText()))->success()->send();
    }

    protected function statusText(): string
    {
        $launchAt = Launch::launchAt();
        $local = $launchAt?->copy()->setTimezone(Setting::get('launch_timezone') ?: 'UTC');

        if (! Launch::isPrelaunch()) {
            $text = '🟢 <strong>الموقع مفتوح للجميع.</strong>';
            if (Setting::get('prelaunch_enabled') && $launchAt?->isPast()) {
                $text .= ' (انتهى العداد وفُتح الموقع تلقائياً)';
            }

            return $text;
        }

        $text = '🟠 <strong>وضع ما قبل الإطلاق مفعّل:</strong> الزوار يرون صفحة الإطلاق ونموذج الإضافة فقط.';

        if ($launchAt?->isFuture()) {
            $text .= '<br>موعد الإطلاق: <strong dir="ltr">'.e($local->format('Y-m-d H:i')).'</strong> ('.e(self::TIMEZONES[Setting::get('launch_timezone')] ?? 'UTC').')'
                .' — بعد '.e($launchAt->locale('ar')->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE, parts: 3)).'.'
                .(Launch::opensAutomatically() ? ' سيُفتح الموقع تلقائياً عند الموعد.' : ' لن يُفتح تلقائياً.');
        } elseif ($launchAt) {
            $text .= '<br>انتهى موعد العداد، والموقع لا يزال مغلقاً لأن الفتح التلقائي غير مفعّل.';
        }

        return $text.'<br><a href="'.e(route('home', ['preview' => 'prelaunch'])).'" target="_blank" class="text-primary-600 underline">معاينة صفحة الإطلاق</a>';
    }
}
