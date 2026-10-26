<?php

namespace App\Filament\Resources;

use App\Filament\Forms\TranslationFields;
use App\Filament\Resources\PageResource\Pages;
use App\Models\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'المحتوى';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'صفحة';

    protected static ?string $pluralModelLabel = 'صفحات الموقع';

    protected static ?string $recordTitleAttribute = 'title';

    /** Toolbar shared by the main and translated content editors */
    public const EDITOR_TOOLBAR = [
        'h2', 'h3', 'bold', 'italic', 'underline', 'link',
        'bulletList', 'orderedList', 'blockquote', 'undo', 'redo',
    ];

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Group::make()->columnSpan(2)->schema([
                TranslationFields::tabs([
                    'title' => ['label' => 'العنوان', 'required' => true, 'maxLength' => 150],
                    'subtitle' => ['label' => 'نص تعريفي قصير (يظهر أسفل العنوان)', 'textarea' => true, 'rows' => 2, 'maxLength' => 500],
                    'content' => [
                        'label' => 'محتوى الصفحة',
                        'rich' => true,
                        'toolbar' => self::EDITOR_TOOLBAR,
                        'helper' => 'استخدم "عنوان 2" لكل قسم رئيسي، وسيظهر كل قسم كبطاقة منفصلة في الصفحة.',
                    ],
                ]),
            ]),
            Forms\Components\Group::make()->schema([
                Forms\Components\Section::make('الإعدادات')->schema([
                    Forms\Components\TextInput::make('slug')->label('رابط الصفحة')
                        ->prefix('/page/')
                        ->helperText('بالإنجليزية، مثال: about')
                        ->alphaDash()
                        ->unique(ignoreRecord: true),
                    Forms\Components\Toggle::make('is_published')->label('منشورة')->default(true),
                    Forms\Components\Toggle::make('show_in_header')->label('رابط في القائمة العلوية'),
                    Forms\Components\Toggle::make('show_in_footer')->label('رابط في الفوتر')->default(true),
                    Forms\Components\TextInput::make('sort_order')->label('الترتيب')->numeric()->default(0),
                ]),
            ]),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('العنوان')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('slug')->label('الرابط')->prefix('/page/'),
                Tables\Columns\ToggleColumn::make('is_published')->label('منشورة'),
                Tables\Columns\ToggleColumn::make('show_in_header')->label('في القائمة'),
                Tables\Columns\ToggleColumn::make('show_in_footer')->label('في الفوتر'),
                Tables\Columns\TextColumn::make('updated_at')->label('آخر تعديل')->since(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')->label('عرض')->icon('heroicon-o-eye')
                    ->url(fn (Page $record) => $record->url(), shouldOpenInNewTab: true),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
