<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Support\SocialLogin;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'الإعدادات';

    protected static ?string $modelLabel = 'مستخدم';

    protected static ?string $pluralModelLabel = 'المستخدمون';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('الاسم')->required(),
            // Optional: Telegram sign-ins have no email until the member adds one
            Forms\Components\TextInput::make('email')->label('البريد')->email()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('password')->label('كلمة المرور')->password()->revealable()
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn ($state) => filled($state))
                ->helperText('اتركها فارغة للإبقاء على كلمة المرور الحالية'),
            Forms\Components\Toggle::make('is_admin')->label('مدير (صلاحية لوحة التحكم)')->inline(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('الاسم')->searchable(),
                Tables\Columns\TextColumn::make('email')->label('البريد')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('socialAccounts.provider')->label('الدخول عبر')->badge()
                    ->formatStateUsing(fn (string $state) => SocialLogin::LABELS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'google' => 'danger',
                        'facebook' => 'info',
                        'telegram' => 'primary',
                        default => 'gray',
                    })
                    ->placeholder('البريد وكلمة المرور'),
                Tables\Columns\IconColumn::make('email_verified_at')->label('البريد مؤكَّد')->boolean()
                    ->state(fn (User $record) => $record->hasVerifiedEmail()),
                Tables\Columns\TextColumn::make('reviews_count')->counts('reviews')->label('التقييمات'),
                Tables\Columns\IconColumn::make('is_admin')->label('مدير')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label('التسجيل')->since()->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_admin')->label('مدير'),
                Tables\Filters\TernaryFilter::make('email_verified_at')->label('البريد مؤكَّد')->nullable(),
            ])
            ->actions([
                Tables\Actions\Action::make('verify_email')->label('تأكيد البريد يدوياً')->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn (User $record) => ! $record->hasVerifiedEmail())
                    ->requiresConfirmation()
                    ->action(fn (User $record) => $record->forceFill(['email_verified_at' => now(), 'verification_code' => null])->save()),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->hidden(fn (User $record) => $record->is(auth()->user())),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageUsers::route('/'),
        ];
    }
}
