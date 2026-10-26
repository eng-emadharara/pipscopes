<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactMessageResource\Pages;
use App\Models\ContactMessage;
use App\Models\User;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationGroup = 'الطلبات والتقييمات';

    protected static ?string $modelLabel = 'رسالة';

    protected static ?string $pluralModelLabel = 'رسائل تواصل معنا';

    public static function getNavigationBadge(): ?string
    {
        $new = ContactMessage::where('status', 'new')->count();

        return $new ?: null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function subjectLabel(string $subject): string
    {
        return trans("trust.contact_subjects.$subject", [], 'ar');
    }

    /**
     * The registered account with the sender's email. Deletion is verified by email only,
     * never by whoever happened to be logged in when the message was sent.
     */
    public static function matchingUser(ContactMessage $record): ?User
    {
        return User::where('email', $record->email)->first();
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->columns(3)->schema([
                Infolists\Components\TextEntry::make('name')->label('الاسم'),
                Infolists\Components\TextEntry::make('email')->label('البريد')->copyable(),
                Infolists\Components\TextEntry::make('subject')->label('الموضوع')->badge()
                    ->formatStateUsing(fn ($state) => static::subjectLabel($state))
                    ->color(fn ($state) => $state === 'account_deletion' ? 'danger' : 'gray'),
                Infolists\Components\TextEntry::make('account')->label('حساب مسجّل بنفس البريد')
                    ->state(fn (ContactMessage $record) => static::matchingUser($record)?->name ?? 'لا يوجد'),
                Infolists\Components\TextEntry::make('created_at')->label('التاريخ')->dateTime('Y-m-d H:i'),
                Infolists\Components\TextEntry::make('ip_address')->label('IP'),
                Infolists\Components\TextEntry::make('message')->label('الرسالة')->columnSpanFull()
                    ->extraAttributes(['class' => 'whitespace-pre-line', 'dir' => 'auto']),
                Infolists\Components\TextEntry::make('admin_notes')->label('ملاحظات إدارية')->columnSpanFull()->placeholder('—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordClasses(fn (ContactMessage $record) => $record->status === 'new' ? 'font-semibold' : null)
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('المرسل')->searchable()
                    ->description(fn (ContactMessage $record) => $record->email),
                Tables\Columns\TextColumn::make('subject')->label('الموضوع')->badge()
                    ->formatStateUsing(fn ($state) => static::subjectLabel($state))
                    ->color(fn ($state) => $state === 'account_deletion' ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('message')->label('الرسالة')->limit(60)->searchable()->wrap(),
                Tables\Columns\TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn ($state) => ContactMessage::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'new' => 'warning',
                        'in_progress' => 'info',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('created_at')->label('التاريخ')->since()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('subject')->label('الموضوع')
                    ->options(collect(ContactMessage::SUBJECTS)->mapWithKeys(fn ($s) => [$s => static::subjectLabel($s)])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    // Opening a new message marks it as being handled
                    ->after(fn (ContactMessage $record) => $record->status === 'new' ? $record->update(['status' => 'in_progress']) : null),
                Tables\Actions\Action::make('reply')->label('رد بالبريد')->icon('heroicon-o-paper-airplane')
                    ->url(fn (ContactMessage $record) => 'mailto:'.$record->email.'?subject='.rawurlencode('Re: '.static::subjectLabel($record->subject)), shouldOpenInNewTab: true),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('done')->label('تمت المعالجة')->icon('heroicon-o-check')->color('success')
                        ->visible(fn (ContactMessage $record) => $record->status !== 'done')
                        ->form([Forms\Components\Textarea::make('admin_notes')->label('ملاحظات (اختياري)')])
                        ->action(fn (ContactMessage $record, array $data) => $record->update([
                            'status' => 'done',
                            'admin_notes' => $data['admin_notes'] ?: $record->admin_notes,
                        ])),
                    static::deleteAccountAction(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** Fulfils an account deletion request: removes the user, their reviews and personal data. */
    protected static function deleteAccountAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('delete_account')
            ->label('حذف حساب المستخدم')
            ->icon('heroicon-o-user-minus')
            ->color('danger')
            ->visible(fn (ContactMessage $record) => $record->subject === 'account_deletion'
                && ($user = static::matchingUser($record)) && ! $user->is_admin)
            ->requiresConfirmation()
            ->modalHeading('حذف الحساب نهائياً')
            ->modalDescription(fn (ContactMessage $record) => sprintf(
                'سيتم حذف حساب «%s» (%s) و%d تقييم نهائياً، ولا يمكن التراجع. تأكد أن الطلب أُرسل من نفس البريد المسجّل.',
                static::matchingUser($record)?->name,
                $record->email,
                static::matchingUser($record)?->reviews()->count() ?? 0,
            ))
            ->modalSubmitActionLabel('حذف نهائياً')
            ->action(function (ContactMessage $record) {
                $user = static::matchingUser($record);

                // Delete reviews one by one so each provider's rating is recalculated
                $user->reviews()->get()->each->delete();
                $user->delete();

                $record->update([
                    'status' => 'done',
                    'admin_notes' => trim(($record->admin_notes ? $record->admin_notes."\n" : '').'تم حذف الحساب بتاريخ '.now()->format('Y-m-d')),
                ]);

                Notification::make()->title('تم حذف الحساب وبياناته')->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContactMessages::route('/'),
        ];
    }
}
