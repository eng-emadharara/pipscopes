<?php

namespace App\Filament\Resources\ImpersonatorResource\Pages;

use App\Filament\Resources\ImpersonatorResource;
use App\Models\Impersonator;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;

class ListImpersonators extends ListRecords
{
    protected static string $resource = ImpersonatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('إضافة حساب منتحل'),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [];
        foreach (Impersonator::STATUSES as $status => $label) {
            $tabs[$status] = Tab::make($label)
                ->badge(Impersonator::where('status', $status)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('status', $status));
        }

        return $tabs + ['all' => Tab::make('الكل')];
    }
}
