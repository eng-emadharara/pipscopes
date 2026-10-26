<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view')->label('عرض على الموقع')->icon('heroicon-o-eye')
                ->url(fn () => $this->record->url(), shouldOpenInNewTab: true),
            Actions\DeleteAction::make(),
        ];
    }
}
