<?php

namespace App\Filament\Resources\ScamCaseResource\RelationManagers;

use App\Support\ScamHelp;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/** What the team did about the case: each new entry reaches every reporter of a confirmed report */
class ActionsRelationManager extends RelationManager
{
    protected static string $relationship = 'actions';

    protected static ?string $title = 'الإجراءات';

    protected static ?string $icon = 'heroicon-o-megaphone';

    protected static ?string $modelLabel = 'إجراء';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('kind')->label('الإجراء')->options(ScamHelp::actions())->required()->default('telegram'),
            Forms\Components\DatePicker::make('done_at')->label('التاريخ')->default(now())->required()->maxDate(now()),
            Forms\Components\Textarea::make('note')->label('ماذا فعلنا (يراه المبلّغون، ويظهر في الصفحة العامة إن نُشر الملف)')
                ->placeholder('مثال: أرسلنا الملف بـ 5 بلاغات إلى ‎@notoscam‎ مع الإثباتات.')
                ->required()->rows(3)->maxLength(1000)->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('done_at')->label('التاريخ')->date('Y-m-d'),
                Tables\Columns\TextColumn::make('kind')->label('الإجراء')->formatStateUsing(fn ($state) => ScamHelp::actions()[$state] ?? $state),
                Tables\Columns\TextColumn::make('note')->label('التفاصيل')->wrap(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('إضافة إجراء')
                    ->modalDescription('يصل إشعار لكل من له بلاغ مؤكد في هذا الملف.'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
