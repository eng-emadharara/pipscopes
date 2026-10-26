<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ReviewResource;
use App\Models\Review;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestReviews extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'أحدث التقييمات';

    public function table(Table $table): Table
    {
        return $table
            ->query(Review::query()->with(['provider', 'user'])->latest()->limit(8))
            ->paginated(false)
            ->columns(ReviewResource::tableColumns())
            ->actions(ReviewResource::tableActions());
    }
}
