<?php

namespace App\Filament\Resources\AnnouncementResource\Pages;

use App\Filament\Resources\AnnouncementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAnnouncement extends EditRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('test')->label('أرسل تجربة لي')->icon('heroicon-o-paper-airplane')->color('gray')
                ->action(fn () => AnnouncementResource::sendTest($this->getRecord())),
            Actions\Action::make('send')->label('إرسال الآن')->icon('heroicon-o-megaphone')->color('success')
                ->visible(fn () => $this->getRecord()->isEditable())
                ->requiresConfirmation()->modalHeading('إرسال الإعلان الآن؟')
                ->modalDescription(fn () => AnnouncementResource::estimateText((array) $this->getRecord()->audience).'. لا يمكن التراجع بعد الإرسال. احفظ تعديلاتك أولاً.')
                ->action(function () {
                    AnnouncementResource::sendNow($this->getRecord());
                    $this->refreshFormData(['status']);
                    $this->redirect($this->getResource()::getUrl('index'));
                }),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return AnnouncementResource::withStatus($data);
    }

    /** A sent announcement is read-only */
    protected function getFormActions(): array
    {
        return $this->getRecord()->isEditable() ? parent::getFormActions() : [];
    }
}
