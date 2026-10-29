<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\SignupGuard;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\File;

/** Switches for App\Support\SignupGuard: throwaway emails, alias accounts, sign-ups per IP and holding new accounts' reviews. */
class SignupProtection extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'حماية التسجيل';

    protected static ?string $title = 'حماية التسجيل والتقييمات';

    protected static ?string $slug = 'signup-protection';

    protected static string $view = 'filament.pages.settings-form';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(collect(SignupGuard::DEFAULTS)->map(fn ($default, $key) => SignupGuard::setting($key))->all());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('updateList')->label('تحديث قائمة البريد المؤقت')->icon('heroicon-o-arrow-path')->color('gray')
                ->action(function () {
                    try {
                        $count = SignupGuard::updateDisposableList();
                        Notification::make()->title('تم تحديث القائمة')->body(number_format($count).' نطاق مؤقت محظور.')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('تعذّر التحديث')->body(\Illuminate\Support\Str::limit($e->getMessage(), 300))->danger()->send();
                    }
                }),
        ];
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Forms\Components\Section::make('البريد الإلكتروني عند التسجيل')
                ->description('يُطبّق على التسجيل بالإيميل وعلى إضافة بريد لحسابات تيليجرام. حسابات Google لا تتأثر.')
                ->columns(2)->schema([
                    Forms\Components\Toggle::make('block_disposable')->label('رفض البريد المؤقت (10minutemail وأمثاله)')
                        ->helperText(fn () => $this->listInfo()),
                    Forms\Components\Toggle::make('check_mx')->label('رفض النطاقات التي لا تستقبل بريداً')
                        ->helperText('مثل gmial.com أو نطاق مختلَق. إذا تعذّر الفحص نفسه يُقبل البريد.'),
                    Forms\Components\Toggle::make('block_aliases')->label('منع الحساب الثاني على نفس الصندوق')
                        ->helperText('ahmad+1@gmail.com و a.hmad@gmail.com تصل لنفس صندوق ahmad@gmail.com، فتُعامل كحساب موجود.'),
                    Forms\Components\TextInput::make('ip_daily_limit')->label('أقصى عدد حسابات جديدة من نفس IP خلال 24 ساعة')
                        ->numeric()->minValue(0)->maxValue(100)->required()
                        ->helperText('0 = بلا حد. انتبه أن شبكات الجوال والمقاهي قد تشترك بنفس IP.'),
                    Forms\Components\Textarea::make('blocked_domains')->label('نطاقات إضافية محظورة')->rows(4)
                        ->helperText('نطاق في كل سطر، مثل example.com.'),
                    Forms\Components\Textarea::make('allowed_domains')->label('نطاقات مسموحة دائماً')->rows(4)
                        ->helperText('تتجاوز كل الفحوصات أعلاه، لو حُظر نطاق حقيقي بالخطأ.'),
                ]),

            Forms\Components\Section::make('مراجعة التقييمات المشبوهة')
                ->description('التقييم المحجوز يظهر في «التقييمات» بحالة «قيد المراجعة» مع السبب، ويُنشر بضغطة «نشر».')
                ->columns(2)->schema([
                    Forms\Components\Toggle::make('hold_new_reviews')->label('حجز تقييمات الحسابات الجديدة للمراجعة'),
                    Forms\Components\TextInput::make('new_account_days')->label('الحساب يعتبر جديداً لمدة (أيام)')
                        ->numeric()->minValue(0)->maxValue(90)->required(),
                    Forms\Components\Toggle::make('hold_same_ip')->label('حجز التقييم إذا قيّم حساب آخر نفس القناة من نفس IP')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    protected function listInfo(): string
    {
        $count = count(SignupGuard::disposableDomains());

        return File::exists(SignupGuard::listPath())
            ? number_format($count).' نطاق، آخر تحديث '.\Carbon\Carbon::createFromTimestamp(File::lastModified(SignupGuard::listPath()))->diffForHumans().'. تتحدث أسبوعياً.'
            : 'القائمة المختصرة فقط ('.$count.' نطاق). اضغط «تحديث قائمة البريد المؤقت» بالأعلى لتحميل القائمة الكاملة.';
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $state['blocked_domains'] = implode("\n", SignupGuard::parseDomains($state['blocked_domains']));
        $state['allowed_domains'] = implode("\n", SignupGuard::parseDomains($state['allowed_domains']));

        Setting::put([SignupGuard::SETTING => $state]);
        $this->form->fill($state);

        Notification::make()->title('تم حفظ إعدادات الحماية')->success()->send();
    }
}
