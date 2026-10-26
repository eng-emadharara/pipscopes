<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ProviderResource;
use App\Filament\Resources\ProviderResource\RelationManagers\ChangesRelationManager;
use App\Models\ProviderChange;
use App\Models\ProviderOwner;
use App\Models\Setting;
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
use Illuminate\Support\HtmlString;

/** Switches the owner portal on/off and lists recent changes owners made to their channels. */
class OwnerPortalSettings extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'بوابة أصحاب القنوات';

    protected static ?string $title = 'بوابة أصحاب القنوات';

    protected static ?string $slug = 'owner-portal';

    protected static string $view = 'filament.pages.owner-portal-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function getNavigationBadge(): ?string
    {
        // Link changes in the last week deserve a look
        $sensitive = ProviderChange::where('created_at', '>=', now()->subDays(7))->get()
            ->filter->touchesSensitiveFields()->count();

        return $sensitive ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function mount(): void
    {
        $this->form->fill(['owner_portal_enabled' => (bool) Setting::get('owner_portal_enabled', false)]);
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\Toggle::make('owner_portal_enabled')->label('تفعيل بوابة أصحاب القنوات')
                    ->helperText(new HtmlString(
                        'عند التفعيل يستطيع أصحاب القنوات الدخول من <a href="'.e(route('owner.login')).'" target="_blank" class="text-primary-600 underline" dir="ltr">'.e(route('owner.login')).'</a> '
                        .'ثم يديرون قناتهم من صفحتها على الموقع مباشرة: يردون تحت كل تقييم ويعدّلون بياناتها من زر "تعديل بيانات القناة". '
                        .'عند الإطفاء يُغلق الدخول ويُسجَّل خروج الجميع. '
                        .'لا يوجد تسجيل ذاتي: أنشئ الحسابات من صفحة كل مقدم خدمة ← "حسابات صاحب القناة".'
                    )),
                Forms\Components\Placeholder::make('accounts')->label('الحسابات')
                    ->content(fn () => ProviderOwner::where('is_active', true)->count().' حساب مفعّل لـ '
                        .ProviderOwner::where('is_active', true)->distinct('provider_id')->count('provider_id').' قناة'),
            ]),
        ]);
    }

    public function save(): void
    {
        $enabled = (bool) $this->form->getState()['owner_portal_enabled'];
        Setting::put(['owner_portal_enabled' => $enabled]);

        Notification::make()->title($enabled ? 'تم تفعيل بوابة أصحاب القنوات' : 'تم إطفاء بوابة أصحاب القنوات')->success()->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('آخر تعديلات أصحاب القنوات')
            ->description('⚠️ تشير إلى تغيير في الروابط الرسمية، وهو ما يستحق التحقق منه.')
            ->query(ProviderChange::query()->with(['provider', 'owner'])->latest())
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('التاريخ')->since(),
                Tables\Columns\TextColumn::make('provider.name')->label('القناة')
                    ->url(fn (ProviderChange $r) => ProviderResource::getUrl('edit', ['record' => $r->provider])),
                Tables\Columns\TextColumn::make('owner.name')->label('بواسطة')->placeholder('حساب محذوف'),
                Tables\Columns\TextColumn::make('diff')->label('ما الذي تغيّر')->wrap()
                    ->formatStateUsing(fn ($state, ProviderChange $record) => ChangesRelationManager::describe($record)),
            ])
            ->paginated([10, 25]);
    }
}
