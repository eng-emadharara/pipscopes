<?php

namespace App\Filament\Resources\ScamCaseResource\RelationManagers;

use App\Filament\Resources\ScamReportResource;
use App\Models\ScamReport;
use App\Support\ScamHelp;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ReportsRelationManager extends RelationManager
{
    protected static string $relationship = 'reports';

    protected static ?string $title = 'البلاغات';

    protected static ?string $icon = 'heroicon-o-exclamation-triangle';

    protected static ?string $modelLabel = 'بلاغ';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('scam_type')->label('النوع')->formatStateUsing(fn ($state) => ScamHelp::types()[$state][1] ?? $state)->wrap(),
                Tables\Columns\TextColumn::make('amount')->label('المبلغ')->state(fn (ScamReport $r) => $r->amountLabel())->placeholder('—'),
                Tables\Columns\TextColumn::make('paid_at')->label('تاريخ الدفع')->date('Y-m-d')->placeholder('—'),
                Tables\Columns\TextColumn::make('user.name')->label('العضو'),
                Tables\Columns\TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn ($state) => ScamReport::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => ['pending' => 'warning', 'confirmed' => 'success'][$state] ?? 'gray'),
            ])
            ->actions([
                Tables\Actions\Action::make('open')->label('مراجعة')->icon('heroicon-o-eye')
                    ->url(fn (ScamReport $r) => ScamReportResource::getUrl('view', ['record' => $r])),
            ]);
    }
}
