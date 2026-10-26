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

/** Switches the optional homepage sections on/off. */
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
            'home_sections' => HomeSections::all(),
            'telegram_join' => Setting::get('telegram_join', []),
        ]);
    }

    public function form(Form $form): Form
    {
        $telegram = Site::socials()['telegram'] ?? null;

        return $form->statePath('data')->schema([
            Forms\Components\Section::make()->schema(
                collect(HomeSections::SECTIONS)->map(fn (string $label, string $key) => Forms\Components\Toggle::make("home_sections.$key")
                    ->label($label)
                    ->helperText(self::HELP[$key]))->values()->all()
            ),

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

        $sections = collect($state['home_sections'] ?? [])
            ->only(array_keys(HomeSections::SECTIONS))
            ->map(fn ($on) => (bool) $on)
            ->all();

        Setting::put(['home_sections' => $sections]);

        Notification::make()->title('تم حفظ أقسام الصفحة الرئيسية')->success()->send();
    }
}
