<?php

namespace App\Filament\Resources\HomePickResource\Pages;

use App\Filament\Resources\HomePickResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageHomePicks extends ManageRecords
{
    protected static string $resource = HomePickResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('home')->label('عرض الصفحة الرئيسية')->icon('heroicon-o-eye')->color('gray')
                ->url(fn () => route('home'), shouldOpenInNewTab: true),
            Actions\CreateAction::make()->label('إضافة قناة'),
        ];
    }
}
