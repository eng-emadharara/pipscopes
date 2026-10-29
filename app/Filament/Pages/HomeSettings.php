<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\HomeSections;
use App\Support\Site;
use Illuminate\Support\HtmlString;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/** The homepage sections: their order, on/off, titles and counts (App\Support\HomeSections). */
class HomeSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'أقسام الصفحة الرئيسية';

    protected static ?string $title = 'أقسام الصفحة الرئيسية';

    protected static ?string $slug = 'home-sections';

    protected static string $view = 'filament.pages.home-settings';

    /** Shown under each toggle */
    protected const HELP = [
        'categories' => 'بطاقات التصنيفات تحت مربع البحث.',
        'picks' => 'القنوات التي يختارها الفريق، بترتيبك وملاحظتك على كل قناة. تُضاف من: المحتوى ← اختيارات الفريق. يختفي القسم إن لم تكن فيه قنوات.',
        'top_rated' => 'القنوات الأعلى تقييماً، والمميزة منها أولاً.',
        'latest_reviews' => 'آخر التقييمات المنشورة.',
        'cta' => 'بطاقة «تقدّم خدمة توصيات أو نسخ صفقات؟» مع زر إرسال البيانات.',
        'account_check' => 'مربع يلصق فيه الزائر رابط أو معرّف حساب تواصل معه، فيعرف إن كان حساباً رسمياً لقناة مسجلة، أو حساباً منتحلاً مؤكداً، أو غير معروف لدينا.',
        'alerts' => 'أحدث الحسابات المنتحلة المؤكدة، والقنوات التي أشار 20% أو أكثر من مقيّميها (3 تقييمات على الأقل) إلى حذف صفقات أو نتائج مضللة أو شبهة احتيال. يختفي القسم تلقائياً إن لم يكن فيه ما يُعرض.',
        'trust' => 'أربع بطاقات تشرح للزائر قواعد المنصة: تقييم واحد لكل حساب مؤكد، لا حذف للتقييمات مقابل المال، إمكانية إخفاء الاسم، وصاحب القناة يرد ولا يحذف.',
        'telegram_join' => 'شريط يدعو الزوار للانضمام لقناة المنصة على تيليجرام، ومعه أيقونات حساباتها الأخرى. يظهر فقط بعد إضافة رابط تيليجرام في إعدادات الموقع.',
    ];

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'layout' => HomeSections::layout(),
            'telegram_join' => Setting::get('telegram_join', []),
        ]);
    }

    public function form(Form $form): Form
    {
        $telegram = Site::socials()['telegram'] ?? null;
        $meta = fn (?string $key, int $i) => HomeSections::SECTIONS[$key][$i] ?? null;

        return $form->statePath('data')->schema([
            Forms\Components\Section::make('ترتيب الأقسام')
                ->description('اسحب الأقسام من المقبض لترتيبها كما تظهر تحت مربع البحث في الصفحة الرئيسية، وشغّل أو أطفئ ما تريد.')
                ->schema([
                    Forms\Components\Repeater::make('layout')->hiddenLabel()
                        ->reorderable()->reorderableWithButtons()->addable(false)->deletable(false)
                        ->collapsible()->collapsed()
                        ->itemLabel(fn (array $state) => ($state['enabled'] ?? false ? '✅ ' : '⏸️ ').($meta($state['key'] ?? null, 0) ?? ''))
                        ->schema([
                            Forms\Components\Hidden::make('key'),
                            Forms\Components\Toggle::make('enabled')->label('ظاهر في الصفحة الرئيسية')->live()
                                ->helperText(fn (Forms\Get $get) => self::HELP[$get('key')] ?? null),
                            Forms\Components\Select::make('limit')->label('العدد المعروض')
                                ->options(array_combine(HomeSections::LIMITS, HomeSections::LIMITS))->selectablePlaceholder(false)
                                ->visible(fn (Forms\Get $get) => $meta($get('key'), 2) !== null),
                            Forms\Components\Tabs::make('titles')
                                ->visible(fn (Forms\Get $get) => $meta($get('key'), 1) !== null)
                                ->tabs(collect(config('trust.locales'))->map(fn (array $m, string $code) => Forms\Components\Tabs\Tab::make($code)->label('العنوان · '.$m['name'])->schema([
                                    Forms\Components\TextInput::make("title.$code")->hiddenLabel()->maxLength(80)
                                        ->placeholder(fn (Forms\Get $get) => $code === 'ar' ? $meta($get('key'), 1) : __($meta($get('key'), 1) ?? '', [], 'en'))
                                        ->helperText('فارغ = العنوان الافتراضي.')
                                        ->extraInputAttributes(['dir' => $m['dir']]),
                                ]))->values()->all()),
                        ]),
                ]),

            Forms\Components\Section::make('قسم قناة تيليجرام')
                ->description('اتركها فارغة لاستخدام النص الافتراضي.')
                ->collapsible()
                ->schema([
                    Forms\Components\Placeholder::make('telegram_link')->label('رابط القناة')
                        ->content(new HtmlString(($telegram
                            ? '<span dir="ltr">'.e($telegram).'</span>'
                            : '<span class="text-danger-600">غير مضاف بعد، لذلك القسم مخفي.</span>')
                            .' — يُعدَّل من <a href="'.e(SiteSettings::getUrl()).'" class="text-primary-600 underline">إعدادات الموقع ← التذييل والتواصل</a>')),
                    Forms\Components\Tabs::make('telegram_texts')->tabs(collect(config('trust.locales'))->map(
                        fn (array $meta, string $code) => Forms\Components\Tabs\Tab::make($code)->label($meta['name'])->schema([
                            Forms\Components\TextInput::make("telegram_join.title.$code")->label('العنوان')->maxLength(80)
                                ->placeholder($code === 'ar' ? 'انضم لقناتنا على تيليجرام' : 'Join our Telegram channel')
                                ->extraInputAttributes(['dir' => $meta['dir']]),
                            Forms\Components\Textarea::make("telegram_join.text.$code")->label('النص')->rows(2)->maxLength(200)
                                ->placeholder($code === 'ar' ? 'تنبيهات بالحسابات المنتحلة، وأحدث القنوات والتقييمات على المنصة، أولاً بأول.' : null)
                                ->extraInputAttributes(['dir' => $meta['dir']]),
                        ])
                    )->values()->all()),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        Setting::put(['telegram_join' => $state['telegram_join'] ?? []]);

        // In the order the admin arranged them; only known sections, tidy values
        $layout = collect($state['layout'] ?? [])
            ->filter(fn ($row) => isset(HomeSections::SECTIONS[$row['key'] ?? '']))
            ->map(fn ($row) => [
                'key' => $row['key'],
                'enabled' => (bool) ($row['enabled'] ?? false),
                'title' => array_filter(array_map('trim', (array) ($row['title'] ?? [])), 'strlen'),
                'limit' => isset($row['limit']) ? (int) $row['limit'] : null,
            ])
            ->values()->all();

        Setting::put(['home_layout' => $layout]);

        Notification::make()->title('تم حفظ أقسام الصفحة الرئيسية')->success()->send();
    }
}
