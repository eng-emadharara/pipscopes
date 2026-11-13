<?php

namespace App\Filament\Resources\ScamReportResource\Pages;

use App\Filament\Resources\ScamReportResource;
use Filament\Resources\Pages\ViewRecord;

class ViewScamReport extends ViewRecord
{
    protected static string $resource = ScamReportResource::class;

    protected function getHeaderActions(): array
    {
        return ScamReportResource::recordActions();
    }
}
