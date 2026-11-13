<?php

namespace App\Filament\Resources\ScamReportResource\Pages;

use App\Filament\Resources\ScamReportResource;
use App\Models\ScamReport;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;

class ListScamReports extends ListRecords
{
    protected static string $resource = ScamReportResource::class;

    public function getTabs(): array
    {
        $tabs = [];
        foreach (ScamReport::STATUSES as $status => $label) {
            $tabs[$status] = Tab::make($label)
                ->badge(ScamReport::where('status', $status)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('status', $status));
        }

        return $tabs + ['all' => Tab::make('الكل')];
    }
}
