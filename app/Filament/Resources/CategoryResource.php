<?php

namespace App\Filament\Resources;

use App\Filament\Forms\TranslationFields;
use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'الإعدادات';

    protected static ?string $modelLabel = 'تصنيف';

    protected static ?string $pluralModelLabel = 'التصنيفات';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TranslationFields::tabs([
                'name' => ['label' => 'الاسم', 'required' => true, 'maxLength' => 100],
                'description' => ['label' => 'الوصف', 'textarea' => true, 'rows' => 3],
            ]),
            Forms\Components\Section::make()->columns(2)->schema([
                Forms\Components\TextInput::make('slug')->label('الرابط (slug)')
                    ->helperText('بالإنجليزية، مثال: signals')
                    ->unique(ignoreRecord: true)->maxLength(100),
                Forms\Components\TextInput::make('icon')->label('أيقونة (إيموجي)')->maxLength(10)->placeholder('📈'),
                Forms\Components\TextInput::make('sort_order')->label('الترتيب')->numeric()->default(0),
                Forms\Components\Toggle::make('is_active')->label('مفعّل')->default(true)->inline(false),
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
                Tables\Columns\TextColumn::make('name')->label('الاسم')->searchable(),
                Tables\Columns\TextColumn::make('slug')->label('الرابط'),
                Tables\Columns\TextColumn::make('providers_count')->counts('providers')->label('عدد المزودين'),
                Tables\Columns\ToggleColumn::make('is_active')->label('مفعّل'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // Providers must always have a category, so only empty categories can be deleted
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (Category $record) => $record->providers()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCategories::route('/'),
        ];
    }
}
