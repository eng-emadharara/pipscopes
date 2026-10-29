<?php

namespace App\Filament\Resources;

use App\Filament\Forms\TranslationFields;
use App\Filament\Resources\HomePickResource\Pages;
use App\Models\HomePick;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** The homepage's "team picks": which channels, in what order, with a note on why. */
class HomePickResource extends Resource
{
    protected static ?string $model = HomePick::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'المحتوى';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'قناة مختارة';

    protected static ?string $pluralModelLabel = 'اختيارات الفريق';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('provider_id')->label('القناة')
                ->relationship('provider', 'name', fn ($query) => $query->where('is_active', true)->orderBy('name'))
                ->searchable()->preload()->required()
                ->unique(ignoreRecord: true)
                ->validationMessages(['unique' => 'هذه القناة موجودة في الاختيارات مسبقاً.'])
                ->columnSpanFull(),
            TranslationFields::tabs([
                'note' => [
                    'label' => 'لماذا اخترناها؟ (اختياري)', 'maxLength' => 160,
                    'placeholder' => 'مثال: سجل نتائج كامل ومنشور منذ سنتين',
                    'helper' => 'سطر قصير يظهر على كارد القناة في الصفحة الرئيسية.',
                ],
            ])->columnSpanFull(),
            Forms\Components\DatePicker::make('starts_at')->label('تظهر من تاريخ')->native(false)
                ->helperText('فارغ = من الآن.'),
            Forms\Components\DatePicker::make('ends_at')->label('حتى تاريخ')->native(false)->afterOrEqual('starts_at')
                ->helperText('فارغ = بدون نهاية. بعدها تختفي تلقائياً.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('اسحب الصفوف لترتيب ظهورها في الصفحة الرئيسية. القسم يُشغَّل ويُرتَّب من: الإعدادات ← أقسام الصفحة الرئيسية.')
            ->reorderable('position')
            ->defaultSort('position')
            ->paginated(false)
            ->modifyQueryUsing(fn ($query) => $query->with('provider'))
            ->columns([
                Tables\Columns\TextColumn::make('position')->label('#')->width('3rem'),
                Tables\Columns\ImageColumn::make('avatar')->label('')->circular()->size(36)
                    ->getStateUsing(fn (HomePick $r) => $r->provider?->avatarUrl()),
                Tables\Columns\TextColumn::make('provider.name')->label('القناة')->weight('bold')
                    ->description(fn (HomePick $r) => $r->note),
                Tables\Columns\TextColumn::make('provider.rating_avg')->label('التقييم')
                    ->formatStateUsing(fn ($state, HomePick $r) => $r->provider?->reviews_count ? number_format((float) $state, 1).' ('.$r->provider->reviews_count.')' : '—'),
                Tables\Columns\TextColumn::make('period')->label('الظهور')
                    ->getStateUsing(fn (HomePick $r) => match (true) {
                        ! $r->provider?->is_active => 'القناة مخفية',
                        $r->isShowing() => $r->ends_at ? 'ظاهرة حتى '.$r->ends_at->format('Y/m/d') : 'ظاهرة',
                        $r->starts_at?->isFuture() => 'تبدأ '.$r->starts_at->format('Y/m/d'),
                        default => 'انتهت',
                    })
                    ->badge()
                    ->color(fn (HomePick $r) => $r->isShowing() ? 'success' : 'gray'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')->label('')->icon('heroicon-o-arrow-top-right-on-square')->tooltip('صفحة القناة')
                    ->url(fn (HomePick $r) => $r->provider ? route('providers.show', $r->provider) : null, shouldOpenInNewTab: true),
                Tables\Actions\EditAction::make()->label('تعديل'),
                Tables\Actions\DeleteAction::make()->label('إزالة')->modalHeading('إزالة القناة من الاختيارات'),
            ])
            ->emptyStateHeading('لا توجد اختيارات بعد')
            ->emptyStateDescription('أضف القنوات التي يراها الفريق قوية لتظهر في قسم «اختيارات الفريق» في الصفحة الرئيسية.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageHomePicks::route('/'),
        ];
    }
}
