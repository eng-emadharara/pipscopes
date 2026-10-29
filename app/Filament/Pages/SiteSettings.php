<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\Site;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;

/** Site identity, SEO, footer and analytics settings (stored as the "site" setting, read through App\Support\Site). */
class SiteSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = -2;

    protected static ?string $navigationLabel = 'إعدادات الموقع';

    protected static ?string $title = 'إعدادات الموقع';

    protected static ?string $slug = 'site-settings';

    protected static string $view = 'filament.pages.site-settings';

    /** Uploaded files, removed from disk when replaced */
    protected const FILES = ['logo', 'favicon', 'og_image'];

    /** The platform's own accounts (footer): key => [label, placeholder] */
    public const SOCIALS = [
        'telegram' => ['تيليجرام', 'https://t.me/...'],
        'x' => ['X / تويتر', 'https://x.com/...'],
        'instagram' => ['إنستغرام', 'https://instagram.com/...'],
        'facebook' => ['فيسبوك', 'https://facebook.com/...'],
        'youtube' => ['يوتيوب', 'https://youtube.com/@...'],
        'tiktok' => ['تيك توك', 'https://tiktok.com/@...'],
        'whatsapp' => ['واتساب', 'https://wa.me/...'],
    ];

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(Site::all());
    }

    /** One tab per language for a translatable text stored as key.{locale} */
    protected function perLanguage(string $key, string $label, bool $textarea = false, ?string $helper = null, ?int $max = null, ?string $placeholder = null): Forms\Components\Tabs
    {
        $base = array_key_first(config('trust.locales'));

        return Forms\Components\Tabs::make($label)->tabs(collect(config('trust.locales'))->map(
            function (array $meta, string $code) use ($key, $label, $textarea, $helper, $max, $placeholder, $base) {
                $field = $textarea
                    ? Forms\Components\Textarea::make("$key.$code")->rows(3)
                    : Forms\Components\TextInput::make("$key.$code");

                return Forms\Components\Tabs\Tab::make($code)->label($meta['name'])->schema([
                    $field->label($label)
                        ->maxLength($max)
                        ->placeholder($code === $base ? $placeholder : null)
                        ->helperText($code === $base ? $helper : 'اختياري: إذا تُرك فارغاً يظهر النص العربي.')
                        ->extraInputAttributes(['dir' => $meta['dir']]),
                ]);
            }
        )->values()->all());
    }

    protected function image(string $key, string $label): Forms\Components\FileUpload
    {
        return Forms\Components\FileUpload::make($key)->label($label)
            ->disk('public')->directory('site')->visibility('public')
            ->image()->maxSize(2048);
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Forms\Components\Tabs::make('settings')->persistTabInQueryString()->tabs([
                Forms\Components\Tabs\Tab::make('الهوية')->icon('heroicon-o-identification')->schema([
                    $this->perLanguage('name', 'اسم الموقع', helper: 'يظهر في الشعار، عنوان المتصفح، الرسائل البريدية وصور المشاركة.', max: 60, placeholder: config('trust.default_name')),
                    Forms\Components\Grid::make(2)->schema([
                        $this->image('logo', 'الشعار (اللوجو)')
                            ->helperText('صورة مربعة (PNG بخلفية شفافة هو الأفضل)، 256×256 على الأقل. بدونه تظهر النجمة الافتراضية.'),
                        $this->image('favicon', 'أيقونة المتصفح (Favicon)')
                            ->acceptedFileTypes(['image/png', 'image/x-icon', 'image/vnd.microsoft.icon'])
                            ->helperText('PNG مربعة 512×512 (تُستخدم أيضاً كأيقونة على شاشة الجوال) أو ملف ICO.'),
                    ]),
                    Forms\Components\Toggle::make('show_name_with_logo')->label('إظهار اسم الموقع بجانب الشعار')->default(true)
                        ->helperText('أطفئه إذا كان الشعار يحتوي على الاسم مكتوباً.'),
                    Forms\Components\ColorPicker::make('theme_color')->label('لون شريط المتصفح على الجوال')->placeholder('#191919'),
                ]),

                Forms\Components\Tabs\Tab::make('محركات البحث والمشاركة')->icon('heroicon-o-magnifying-glass')->schema([
                    $this->perLanguage('meta_description', 'الوصف الافتراضي (Meta description)', textarea: true, max: 300,
                        helper: 'يظهر تحت اسم الموقع في نتائج Google وفي معاينة الروابط. 150–160 حرفاً هو الأنسب. صفحات القنوات تستخدم وصفها الخاص.'),
                    $this->perLanguage('meta_keywords', 'الكلمات المفتاحية', max: 255, placeholder: 'تقييم قنوات التوصيات، نسخ الصفقات، فوركس',
                        helper: 'افصل بينها بفواصل. تأثيرها على Google محدود، لكن بعض محركات البحث تستخدمها.'),
                    $this->image('og_image', 'صورة المشاركة الافتراضية (OG image)')
                        ->helperText('تظهر عند مشاركة رابط الصفحة الرئيسية والصفحات العامة. المقاس المثالي 1200×630. بدونها نولّد صورة تلقائياً باسم الموقع. صفحات القنوات والتقييمات تبقى بصورها الخاصة.'),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('google_verification')->label('رمز التحقق من Google Search Console')
                            ->placeholder('content="…" فقط')->maxLength(100)->extraInputAttributes(['dir' => 'ltr']),
                        Forms\Components\TextInput::make('bing_verification')->label('رمز التحقق من Bing')
                            ->maxLength(100)->extraInputAttributes(['dir' => 'ltr']),
                    ]),
                    Forms\Components\Toggle::make('noindex')->label('منع محركات البحث من فهرسة الموقع')
                        ->helperText('شغّله على نسخة التجربة فقط. على الموقع الحقيقي يجب أن يبقى مطفأً.'),
                ]),

                Forms\Components\Tabs\Tab::make('التذييل والتواصل')->icon('heroicon-o-chat-bubble-left-right')->schema([
                    $this->perLanguage('footer_about', 'نبذة في التذييل', textarea: true, max: 400),
                    $this->perLanguage('disclaimer', 'نص التنبيه (إخلاء المسؤولية)', textarea: true, max: 500),
                    Forms\Components\TextInput::make('contact_email')->label('بريد التواصل العام')->email()->maxLength(150)
                        ->helperText('يظهر في التذييل. اتركه فارغاً لإخفائه.')->extraInputAttributes(['dir' => 'ltr']),
                    Forms\Components\TextInput::make('telegram_contact')->label('رابط المراسلة المباشرة على تيليجرام')->url()->maxLength(255)
                        ->placeholder('https://t.me/pipscopes?direct')->regex('#^https://t\.me/#')
                        ->validationMessages(['regex' => 'يجب أن يبدأ الرابط بـ https://t.me/'])
                        ->helperText('يظهر زر «راسلنا على تيليجرام» في التذييل وصفحة تواصل معنا وصفحة إضافة قناة وفي الإيميلات. اتركه فارغاً لإخفائه.')
                        ->extraInputAttributes(['dir' => 'ltr']),
                    Forms\Components\Fieldset::make('حسابات المنصة على مواقع التواصل')->columns(2)->schema(
                        collect(self::SOCIALS)->map(fn (array $meta, string $key) => Forms\Components\TextInput::make("socials.$key")
                            ->label($meta[0])->url()->maxLength(255)->placeholder($meta[1])->extraInputAttributes(['dir' => 'ltr']))
                            ->values()->all()
                    ),
                ]),

                Forms\Components\Tabs\Tab::make('صفحة القناة')->icon('heroicon-o-presentation-chart-line')->schema([
                    Forms\Components\Toggle::make('telegram_chart')->label('رسم أداء القناة على تيليجرام لآخر 7 أيام')->default(true)
                        ->helperText('ثلاثة خطوط في صفحة كل قناة: المشتركون، متوسط المشاهدات، ونسبة المشاهدات للمشتركين. يظهر فقط للقنوات التي لها يومان على الأقل من الأرقام المسجّلة.'),
                ]),

                Forms\Components\Tabs\Tab::make('الإحصائيات')->icon('heroicon-o-chart-bar')->schema([
                    Forms\Components\TextInput::make('ga_id')->label('معرّف Google Analytics')
                        ->placeholder('G-XXXXXXXXXX')->regex('/^G-[A-Z0-9]+$/')->maxLength(30)->extraInputAttributes(['dir' => 'ltr'])
                        ->helperText('يُضاف كود التتبع تلقائياً لكل صفحات الموقع (وليس لوحة التحكم).'),
                    Forms\Components\Textarea::make('head_code')->label('كود إضافي داخل <head>')->rows(5)
                        ->extraInputAttributes(['dir' => 'ltr', 'class' => 'font-mono text-xs'])
                        ->helperText('لأدوات مثل Meta Pixel أو Microsoft Clarity. يُنفّذ كما هو في كل صفحات الموقع، فالصق فقط كوداً من مصدر تثق به.'),
                ]),
            ]),
        ]);
    }

    public function save(): void
    {
        $old = Site::all();
        $data = $this->form->getState();

        // Remove files that were replaced or cleared
        foreach (self::FILES as $key) {
            if (filled($old[$key] ?? null) && ($old[$key] ?? null) !== ($data[$key] ?? null)) {
                Storage::disk('public')->delete($old[$key]);
            }
        }

        $data['theme_color'] = filled($data['theme_color'] ?? null) ? $data['theme_color'] : null;
        $data['google_verification'] = $this->verificationCode($data['google_verification'] ?? null);
        $data['bing_verification'] = $this->verificationCode($data['bing_verification'] ?? null);

        Setting::put([Site::KEY => $data]);

        Notification::make()->title('تم حفظ إعدادات الموقع')->success()->send();
    }

    /** People often paste the whole <meta> tag; keep just its content value. */
    protected function verificationCode(?string $value): ?string
    {
        $value = trim((string) $value);
        if (preg_match('/content=["\']([^"\']+)["\']/', $value, $m)) {
            $value = $m[1];
        }

        return $value !== '' ? $value : null;
    }
}
