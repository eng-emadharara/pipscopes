<?php

namespace App\Filament\Resources\ProviderSubmissionResource\Pages;

use App\Filament\Resources\ProviderSubmissionResource;
use App\Models\ProviderSubmission;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;

class ListProviderSubmissions extends ListRecords
{
    protected static string $resource = ProviderSubmissionResource::class;

    public function getTabs(): array
    {
        $tabs = [];
        foreach (ProviderSubmission::STATUSES as $status => $label) {
            $tabs[$status] = Tab::make($label)
                ->badge(ProviderSubmission::where('status', $status)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('status', $status));
        }

        return $tabs + ['all' => Tab::make('الكل')];
    }
}
