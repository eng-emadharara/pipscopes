<?php

namespace App\Filament\Pages;

use App\Support\SocialKeys;
use App\Support\SocialLogin;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;

/** Google / Facebook / Telegram sign-in keys, the only place they are set (stored through App\Support\SocialKeys). */
class SocialLoginSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'مفاتيح تسجيل الدخول';

    protected static ?string $title = 'مفاتيح تسجيل الدخول الاجتماعي';

    protected static ?string $slug = 'social-login';

    protected static string $view = 'filament.pages.social-login-settings';

    /** provider => [where to get the keys, where to register the callback] */
    protected const GUIDES = [
        'google' => 'من <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="underline">Google Cloud Console</a> ← Credentials ← OAuth client (Web application). أضف رابط العودة أدناه في "Authorized redirect URIs".',
        'facebook' => 'من <a href="https://developers.facebook.com/apps" target="_blank" class="underline">Meta for Developers</a> ← تطبيق فيه منتج "Facebook Login". أضف رابط العودة أدناه في "Valid OAuth Redirect URIs".',
        'telegram' => 'أنشئ بوتاً عبر <a href="https://t.me/BotFather" target="_blank" class="underline">@BotFather</a> وانسخ التوكن، ثم أرسل له الأمر /setdomain واختر البوت وأرسل نطاق الموقع أدناه.',
    ];

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $stored = SocialKeys::stored();

        // Secrets are never sent back to the browser; an empty secret field keeps the saved one
        $this->form->fill([
            ...collect(SocialKeys::FIELDS)->map(fn (array $fields, string $provider) => collect($fields)
                ->map(fn (array $field, string $key) => $field[1] ? null : ($stored[$provider][$key] ?? null))
                ->all())->all(),
            'google_callback' => url('/auth/google/callback'),
            'facebook_callback' => url('/auth/facebook/callback'),
            'telegram_callback' => parse_url(url('/'), PHP_URL_HOST),
        ]);
    }

    public function form(Form $form): Form
    {
        $sections = collect(SocialKeys::FIELDS)->map(fn (array $fields, string $provider) => Forms\Components\Section::make(SocialLogin::LABELS[$provider])
            ->description(new HtmlString(self::GUIDES[$provider]))
            ->collapsible()
            ->headerActions([
                Forms\Components\Actions\Action::make("clear_$provider")->label('حذف المفاتيح من اللوحة')
                    ->icon('heroicon-o-trash')->color('danger')->size('sm')
                    ->visible(fn () => filled(SocialKeys::stored()[$provider] ?? null))
                    ->requiresConfirmation()
                    ->modalHeading('حذف مفاتيح '.SocialLogin::LABELS[$provider].'؟')
                    ->modalDescription('سيظهر الزر في صفحة الدخول بحالة "قريباً" حتى تُضاف مفاتيح جديدة.')
                    ->action(fn () => $this->clear($provider)),
            ])
            ->schema([
                Forms\Components\Placeholder::make("{$provider}_status")->hiddenLabel()
                    ->content(fn () => new HtmlString($this->statusText($provider))),

                Forms\Components\TextInput::make("{$provider}_callback")->label($provider === 'telegram' ? 'نطاق الموقع (لأمر /setdomain)' : 'رابط العودة (Redirect URI)')
                    ->readOnly()
                    ->dehydrated(false)
                    ->extraInputAttributes(['dir' => 'ltr'])
                    ->suffixAction(Forms\Components\Actions\Action::make('copy')->icon('heroicon-o-clipboard-document')->label('نسخ')
                        ->alpineClickHandler("window.navigator.clipboard.writeText(\$wire.data.{$provider}_callback); \$tooltip('تم النسخ', { theme: \$store.theme, timeout: 2000 })")),

                Forms\Components\Grid::make(2)->schema(collect($fields)->map(function (array $field, string $key) use ($provider) {
                    [$label, $secret] = $field;

                    $input = Forms\Components\TextInput::make("$provider.$key")->label($label)
                        ->maxLength(500)
                        ->autocomplete('off')
                        ->extraInputAttributes(['dir' => 'ltr'])
                        ->hint(fn () => SocialKeys::isSet($provider, $key) ? 'محفوظ' : 'غير مضبوط')
                        ->hintColor(fn () => SocialKeys::isSet($provider, $key) ? 'success' : 'gray');

                    if ($provider === 'telegram') {
                        $input->regex('/^\d+:[\w-]+$/')->validationMessages(['regex' => 'التوكن بالشكل 123456789:ABC... كما يرسله BotFather.']);
                    }

                    return $secret
                        ? $input->password()->revealable()
                            ->placeholder(fn () => SocialKeys::isSet($provider, $key) ? '•••••••• (اتركه فارغاً للإبقاء على المحفوظ)' : null)
                        : $input;
                })->values()->all()),
            ]))->values()->all();

        return $form->statePath('data')->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\Placeholder::make('intro')->hiddenLabel()->content(new HtmlString(
                    'المفاتيح تُضبط من هنا فقط وتُخزَّن مشفّرة (لا تُقرأ من ملف <code>.env</code>). '
                    .'يظهر زر تسجيل الدخول للزوار فقط عندما تكتمل مفاتيحه.'
                )),
            ]),
            ...$sections,
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $stored = SocialKeys::stored();

        $keys = collect(SocialKeys::FIELDS)->map(fn (array $fields, string $provider) => collect($fields)
            ->map(fn (array $field, string $key) => $field[1] && blank($data[$provider][$key] ?? null)
                ? ($stored[$provider][$key] ?? null)
                : ($data[$provider][$key] ?? null))
            ->all())->all();

        SocialKeys::save($keys);

        Notification::make()->title('تم حفظ مفاتيح تسجيل الدخول')->success()->send();

        // Reload so the status of each provider reflects the new keys
        $this->redirect(static::getUrl());
    }

    public function clear(string $provider): void
    {
        abort_unless(isset(SocialKeys::FIELDS[$provider]), 404);

        $keys = SocialKeys::stored();
        unset($keys[$provider]);
        SocialKeys::save($keys);

        Notification::make()->title('تم حذف مفاتيح '.SocialLogin::LABELS[$provider])->success()->send();

        $this->redirect(static::getUrl());
    }

    protected function statusText(string $provider): string
    {
        if (! SocialLogin::isEnabled($provider)) {
            return '⚪ <strong>غير مفعّل:</strong> الزر يظهر في صفحة الدخول بحالة "قريباً" حتى تكتمل المفاتيح.';
        }

        return '🟢 <strong>مفعّل:</strong> الزوار يمكنهم الدخول عبر '.SocialLogin::LABELS[$provider].'.';
    }
}
