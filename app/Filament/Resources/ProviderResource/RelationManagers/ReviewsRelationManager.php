<?php

namespace App\Filament\Resources\ProviderResource\RelationManagers;

use App\Filament\Resources\ReviewResource;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ReviewsRelationManager extends RelationManager
{
    protected static string $relationship = 'reviews';

    protected static ?string $title = 'التقييمات';

    protected static ?string $icon = 'heroicon-o-star';

    public static function getBadge(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->reviews()->count() ?: null;
    }

    protected static ?string $modelLabel = 'تقييم';

    public function form(Form $form): Form
    {
        return ReviewResource::form($form);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns(ReviewResource::tableColumns(withProvider: false))
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('إضافة تقييم'),
            ])
            ->actions(ReviewResource::tableActions())
            ->bulkActions(ReviewResource::tableBulkActions());
    }
}
