<?php

namespace App\Filament\Resources\ProviderSubmissionResource\Pages;

use App\Filament\Resources\ProviderSubmissionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProviderSubmission extends EditRecord
{
    protected static string $resource = ProviderSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ProviderSubmissionResource::convertAction(Actions\Action::class),
            Actions\DeleteAction::make(),
        ];
    }
}
