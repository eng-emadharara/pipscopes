<?php

namespace App\Filament\Resources\ScamCaseResource\Pages;

use App\Filament\Resources\ScamCaseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditScamCase extends EditRecord
{
    protected static string $resource = ScamCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('public')->label('الصفحة العامة')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                ->visible(fn () => $this->record->is_public && $this->record->reports_count > 0)
                ->url(fn () => route('scam-cases.show', $this->record), shouldOpenInNewTab: true),
            Actions\DeleteAction::make(),
        ];
    }
}
