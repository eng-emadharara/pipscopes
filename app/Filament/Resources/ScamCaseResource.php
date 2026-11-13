<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScamCaseResource\Pages;
use App\Filament\Resources\ScamCaseResource\RelationManagers;
use App\Models\ScamCase;
use App\Support\ScamHelp;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** One case per account / wallet / broker: its reports, what the team did (every reporter is told), and whether it is public. */
class ScamCaseResource extends Resource
{
    protected static ?string $model = ScamCase::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder-open';

    protected static ?string $navigationGroup = 'الطلبات والتقييمات';

    protected static ?string $modelLabel = 'ملف نصب';

    protected static ?string $pluralModelLabel = 'ملفات النصب';

    /** Cases ready to act on that nothing was done about yet */
    public static function getNavigationBadge(): ?string
    {
        return (string) ScamCase::where('reports_count', '>=', ScamHelp::ACT_AT)->doesntHave('actions')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                Forms\Components\Placeholder::make('target')->label('الجهة')
                    ->content(fn (?ScamCase $record) => $record ? $record->targetLabel().': '.$record->target_display : '—'),
                Forms\Components\Placeholder::make('counts')->label('البلاغات المؤكدة')
                    ->content(fn (?ScamCase $record) => $record ? $record->reports_count.' · ≈ $'.number_format((float) $record->total_usd) : '—'),
                Forms\Components\TextInput::make('target_display')->label('اسم العرض')->required()->maxLength(255),
                Forms\Components\Select::make('provider_id')->label('القناة على المنصة (إن كانت مدرجة)')
                    ->relationship('provider', 'name')->searchable()->preload()
                    ->helperText('عند نشر الملف يصل تنبيه لمتابعي القناة، ويظهر الملف في صفحتها وفي فحصها.'),
                Forms\Components\Textarea::make('public_summary')->label('ملخص للعامة')->rows(4)->columnSpanFull()
                    ->placeholder('مثال: يطلب المشتركون دفع اشتراك VIP عبر USDT ثم يحظرهم. أكدنا 4 بلاغات بإثباتات دفع ومحادثات.')
                    ->helperText('لا تذكر أسماء المبلّغين أو تفاصيلهم. استخدم «بلاغات» وليس «نصّاب».'),
                Forms\Components\Toggle::make('is_public')->label('نشر الملف للعامة')
                    ->helperText('يظهر في صفحة عامة، وفي صفحة القناة وفحصها. انشر فقط ملفاً فيه بلاغات مؤكدة بإثباتات كافية.')
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('reports_count', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('target_display')->label('الجهة')->searchable()->wrap()
                    ->extraAttributes(['dir' => 'ltr'])
                    ->description(fn (ScamCase $c) => $c->targetLabel().($c->provider ? ' · '.$c->provider->name : '')),
                Tables\Columns\TextColumn::make('reports_count')->label('مؤكدة')->sortable()->badge()
                    ->color(fn ($state) => $state >= ScamHelp::ACT_AT ? 'danger' : ($state > 0 ? 'warning' : 'gray')),
                Tables\Columns\TextColumn::make('pending_count')->label('بانتظار المراجعة')
                    ->state(fn (ScamCase $c) => $c->reports()->where('status', 'pending')->count() ?: null)->placeholder('—'),
                Tables\Columns\TextColumn::make('total_usd')->label('الخسائر ≈ $')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('actions_count')->label('إجراءات')->counts('actions'),
                Tables\Columns\IconColumn::make('is_public')->label('منشور')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->label('آخر نشاط')->since()->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('ready')->label('جاهزة للتحرك')
                    ->query(fn ($query) => $query->where('reports_count', '>=', ScamHelp::ACT_AT)),
                Tables\Filters\TernaryFilter::make('is_public')->label('منشور'),
            ])
            ->actions([
                Tables\Actions\Action::make('public')->label('الصفحة العامة')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                    ->visible(fn (ScamCase $c) => $c->is_public && $c->reports_count > 0)
                    ->url(fn (ScamCase $c) => route('scam-cases.show', $c), shouldOpenInNewTab: true),
                Tables\Actions\EditAction::make()->label('فتح'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ActionsRelationManager::class,
            RelationManagers\ReportsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListScamCases::route('/'),
            'edit' => Pages\EditScamCase::route('/{record}/edit'),
        ];
    }
}
