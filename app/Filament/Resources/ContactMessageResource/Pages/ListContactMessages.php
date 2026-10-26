<?php

namespace App\Filament\Resources\ContactMessageResource\Pages;

use App\Filament\Resources\ContactMessageResource;
use App\Models\ContactMessage;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;

class ListContactMessages extends ListRecords
{
    protected static string $resource = ContactMessageResource::class;

    public function getTabs(): array
    {
        $tabs = [];
        foreach (ContactMessage::STATUSES as $status => $label) {
            $tabs[$status] = Tab::make($label)
                ->badge(ContactMessage::where('status', $status)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('status', $status));
        }

        return $tabs + ['all' => Tab::make('الكل')];
    }
}
