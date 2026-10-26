<?php

namespace App\Filament\Resources\ProviderResource\RelationManagers;

use App\Models\ProviderOwner;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/** Admins create the channel owner's login and set/reset its password to share with them. */
class OwnersRelationManager extends RelationManager
{
    protected static string $relationship = 'owners';

    protected static ?string $title = 'حسابات صاحب القناة';

    protected static ?string $icon = 'heroicon-o-key';

    public static function getBadge(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->owners()->count() ?: null;
    }

    protected static ?string $modelLabel = 'حساب صاحب قناة';

    protected static function passwordField(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('password')->label('كلمة المرور')
            ->password()->revealable()->minLength(8)
            ->dehydrated(fn ($state) => filled($state))
            ->suffixAction(Forms\Components\Actions\Action::make('generate')
                ->icon('heroicon-o-sparkles')->tooltip('توليد كلمة مرور قوية')
                ->action(fn (Forms\Set $set) => $set('password', Str::password(12, symbols: false))));
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('الاسم')->required()->maxLength(100),
            Forms\Components\TextInput::make('email')->label('البريد (اسم الدخول)')->email()->required()
                ->unique(ProviderOwner::class, 'email', ignoreRecord: true)
                ->extraInputAttributes(['dir' => 'ltr']),
            static::passwordField()
                ->required(fn (string $operation) => $operation === 'create')
                ->helperText(fn (string $operation) => $operation === 'create'
                    ? 'شاركها مع صاحب القناة. يمكنك تغييرها لاحقاً.'
                    : 'اتركها فارغة للإبقاء على كلمة المرور الحالية.'),
            Forms\Components\Toggle::make('is_active')->label('الحساب مفعّل')->default(true)->inline(false),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->description(fn () => Setting::get('owner_portal_enabled')
                ? 'رابط الدخول: '.route('owner.login')
                : '⚠️ بوابة أصحاب القنوات مطفأة حالياً. فعّلها من الإعدادات ← بوابة أصحاب القنوات.')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('الاسم'),
                Tables\Columns\TextColumn::make('email')->label('البريد')->copyable()->extraAttributes(['dir' => 'ltr']),
                Tables\Columns\ToggleColumn::make('is_active')->label('مفعّل'),
                Tables\Columns\TextColumn::make('last_login_at')->label('آخر دخول')->since()->placeholder('لم يدخل بعد'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('إضافة حساب')
                    // The plain password is only known before it's hashed, so the notice is sent from here
                    ->using(function (array $data, string $model) {
                        $owner = $this->getOwnerRecord()->owners()->create($data);
                        static::shareNotice($owner, $data['password']);

                        return $owner;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('reset_password')->label('تغيير كلمة المرور')->icon('heroicon-o-key')
                    ->fillForm(fn () => ['password' => Str::password(12, symbols: false)])
                    ->form([static::passwordField()->required()->helperText('ستظهر لك بعد الحفظ لتنسخها وترسلها لصاحب القناة.')])
                    ->action(function (ProviderOwner $record, array $data) {
                        $record->update(['password' => $data['password']]);
                        static::shareNotice($record, $data['password']);
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    /** Shows the login details once, ready to copy and send to the channel owner. */
    protected static function shareNotice(ProviderOwner $owner, ?string $password): void
    {
        Notification::make()
            ->title('بيانات الدخول جاهزة للمشاركة')
            ->body("الرابط: ".route('owner.login')."\nالبريد: {$owner->email}\nكلمة المرور: {$password}\n\nانسخها الآن، لن تظهر كلمة المرور مرة أخرى.")
            ->success()
            ->persistent()
            ->send();
    }
}
