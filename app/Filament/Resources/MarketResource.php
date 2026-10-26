<?php

namespace App\Filament\Resources;

use App\Filament\Forms\TranslationFields;
use App\Filament\Resources\MarketResource\Pages;
use App\Models\Market;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MarketResource extends Resource
{
    protected static ?string $model = Market::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'الإعدادات';

    protected static ?string $modelLabel = 'سوق';

    protected static ?string $pluralModelLabel = 'الأسواق';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TranslationFields::tabs([
                'name' => ['label' => 'الاسم', 'required' => true, 'maxLength' => 100],
            ]),
            Forms\Components\Section::make()->columns(2)->schema([
                Forms\Components\TextInput::make('slug')->label('الرابط (slug)')
                    ->helperText('بالإنجليزية، يُستخدم في روابط الفلترة. مثال: gold')
                    ->alphaDash()->unique(ignoreRecord: true)->maxLength(100),
                Forms\Components\TextInput::make('icon')->label('أيقونة (إيموجي)')->maxLength(10)->placeholder('🥇'),
                Forms\Components\TextInput::make('sort_order')->label('الترتيب')->numeric()->default(0),
                Forms\Components\Toggle::make('is_active')->label('مفعّل')->default(true)
                    ->helperText('الأسواق غير المفعّلة لا تظهر في النماذج والفلاتر.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('icon')->label(''),
                Tables\Columns\TextColumn::make('name')->label('الاسم')->searchable()
                    ->description(fn (Market $record) => $record->translations['en']['name'] ?? null),
                Tables\Columns\TextColumn::make('slug')->label('الرابط'),
                Tables\Columns\TextColumn::make('providers_count')->counts('providers')->label('عدد المزودين'),
                Tables\Columns\ToggleColumn::make('is_active')->label('مفعّل'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->modalDescription('سيُزال هذا السوق من كل مقدمي الخدمة المرتبطين به.'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageMarkets::route('/'),
        ];
    }
}
