<?php

namespace App\Filament\Resources\ProviderResource\RelationManagers;

use App\Filament\Resources\ImpersonatorResource;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ImpersonatorsRelationManager extends RelationManager
{
    protected static string $relationship = 'impersonators';

    protected static ?string $title = 'الحسابات المنتحلة';

    protected static ?string $icon = 'heroicon-o-shield-exclamation';

    public static function getBadge(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->impersonators()->count() ?: null;
    }

    public static function getBadgeColor(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): ?string
    {
        return 'danger';
    }

    protected static ?string $modelLabel = 'حساب منتحل';

    public function form(Form $form): Form
    {
        return ImpersonatorResource::form($form);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return ImpersonatorResource::infolist($infolist);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('reports_count', 'desc')
            ->columns(ImpersonatorResource::tableColumns(withProvider: false))
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('إضافة حساب منتحل'),
            ])
            ->actions(ImpersonatorResource::tableActions());
    }
}
