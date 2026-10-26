<?php

namespace App\Filament\Resources\ReviewResource\Pages;

use App\Filament\Resources\ReviewResource;
use App\Models\Review;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;

class ListReviews extends ListRecords
{
    protected static string $resource = ReviewResource::class;

    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('الكل')];
        foreach (Review::STATUSES as $status => $label) {
            $tabs[$status] = Tab::make($label)
                ->badge(Review::where('status', $status)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('status', $status));
        }

        return $tabs;
    }
}
