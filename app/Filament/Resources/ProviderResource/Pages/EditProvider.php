<?php

namespace App\Filament\Resources\ProviderResource\Pages;

use App\Filament\Resources\ProviderResource;
use App\Services\TelegramChannel;
use App\Services\TelegramRefresh;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProvider extends EditRecord
{
    protected static string $resource = ProviderResource::class;

    // The form and the related lists (reviews, impersonators, owner accounts, change log) as tabs at the top
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'بيانات القناة';
    }

    public function getContentTabIcon(): ?string
    {
        return 'heroicon-o-identification';
    }

    // Categories are saved after the record, so the search text is rebuilt once they are in place
    protected function afterSave(): void
    {
        $this->record->refreshSearchIndex();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view')->label('عرض على الموقع')->icon('heroicon-o-eye')
                ->url(fn () => route('providers.show', $this->record), shouldOpenInNewTab: true),
            Actions\Action::make('telegram_refresh')->label('تحديث من تيليجرام')->icon('heroicon-o-arrow-path')->color('gray')
                ->visible(fn () => filled($this->record->telegram_url))
                ->action(function () {
                    $status = TelegramRefresh::refresh($this->record);
                    $this->refreshFormData(['telegram_members', 'avatar']);

                    $status === TelegramChannel::CHANNEL
                        ? Notification::make()->title('تم تحديث بيانات القناة من تيليجرام')->success()->send()
                        : Notification::make()->title('تعذّر التحديث')->body(\App\Http\Controllers\SubmissionController::message($status))->warning()->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
