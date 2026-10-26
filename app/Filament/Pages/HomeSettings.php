<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\HomeSections;
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
    ];

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['home_sections' => HomeSections::all()]);
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Forms\Components\Section::make()->schema(
                collect(HomeSections::SECTIONS)->map(fn (string $label, string $key) => Forms\Components\Toggle::make("home_sections.$key")
                    ->label($label)
                    ->helperText(self::HELP[$key]))->values()->all()
            ),
        ]);
    }

    public function save(): void
    {
        $sections = collect($this->form->getState()['home_sections'] ?? [])
            ->only(array_keys(HomeSections::SECTIONS))
            ->map(fn ($on) => (bool) $on)
            ->all();

        Setting::put(['home_sections' => $sections]);

        Notification::make()->title('تم حفظ أقسام الصفحة الرئيسية')->success()->send();
    }
}
