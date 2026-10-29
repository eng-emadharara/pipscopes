<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProviderResource\RelationManagers\ReviewsRelationManager;
use App\Filament\Resources\ReviewResource\Pages;
use App\Models\Review;
use App\Support\SignupGuard;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationGroup = 'الطلبات والتقييمات';

    protected static ?string $modelLabel = 'تقييم';

    protected static ?string $pluralModelLabel = 'التقييمات';

    public static function getNavigationBadge(): ?string
    {
        $pending = Review::where('status', Review::STATUS_PENDING)->count();

        return $pending ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        $stars = [1 => '★ 1', 2 => '★★ 2', 3 => '★★★ 3', 4 => '★★★★ 4', 5 => '★★★★★ 5'];
        $criteria = collect(__('trust.criteria'))
            ->map(fn ($label, $field) => Forms\Components\Select::make($field)->label($label)->options($stars))
            ->values()
            ->all();

        return $form->schema([
            Forms\Components\Section::make('التقييم')->columns(2)->schema([
                Forms\Components\Select::make('provider_id')->label('مقدم الخدمة')
                    ->relationship('provider', 'name')->searchable()->preload()->required()
                    ->hidden(fn ($livewire) => $livewire instanceof ReviewsRelationManager),
                Forms\Components\Select::make('user_id')->label('المستخدم')
                    ->relationship('user', 'name')->searchable()->preload()->required()
                    ->helperText('الاسم الحقيقي يظهر للإدارة فقط عند إخفاء الاسم'),
                Forms\Components\Select::make('rating')->label('التقييم العام')->options($stars)->required(),
                Forms\Components\Toggle::make('show_name')->label('إظهار اسم المقيّم')->default(true)->inline(false),
                Forms\Components\TextInput::make('title')->label('العنوان')->required()->columnSpanFull(),
                Forms\Components\Textarea::make('body')->label('التفاصيل')->rows(5)->required()->columnSpanFull(),
            ]),
            Forms\Components\Section::make('تحليل سبب التقييم')->columns(2)->collapsible()->schema([
                ...$criteria,
                Forms\Components\CheckboxList::make('pros')->label('الإيجابيات')->options(__('trust.pros'))->columns(2),
                Forms\Components\CheckboxList::make('cons')->label('السلبيات')->options(__('trust.cons'))->columns(2),
                Forms\Components\Select::make('experience_duration')->label('مدة التجربة')->options(__('trust.experience_durations')),
            ]),
            Forms\Components\Section::make('الإشراف')->columns(2)->schema([
                Forms\Components\Select::make('status')->label('الحالة')->options(Review::STATUSES)->default(Review::STATUS_APPROVED)->required(),
                Forms\Components\Textarea::make('admin_note')->label('ملاحظة إدارية (داخلية)'),
                Forms\Components\Textarea::make('provider_reply')->label('رد مقدم الخدمة (يظهر علناً)')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns(static::tableColumns())
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('الحالة')->options(Review::STATUSES),
                Tables\Filters\SelectFilter::make('rating')->label('النجوم')->options([1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5]),
                Tables\Filters\SelectFilter::make('provider')->label('مقدم الخدمة')->relationship('provider', 'name')->searchable()->preload(),
                Tables\Filters\TernaryFilter::make('show_name')->label('الاسم ظاهر'),
                Tables\Filters\Filter::make('reported')->label('عليه بلاغات مفتوحة')
                    ->query(fn ($query) => $query->where('open_reports_count', '>', 0)),
                Tables\Filters\Filter::make('same_ip')->label('حسابات متعددة من نفس IP')
                    ->query(fn ($query) => $query->whereNotNull('ip')->whereExists(fn ($q) => $q->from('reviews as other')
                        ->whereColumn('other.provider_id', 'reviews.provider_id')->whereColumn('other.ip', 'reviews.ip')
                        ->whereColumn('other.user_id', '!=', 'reviews.user_id'))),
                Tables\Filters\SelectFilter::make('hold_reason')->label('سبب الانتظار')->options(SignupGuard::HOLD_REASONS),
            ])
            ->actions(static::tableActions())
            ->bulkActions(static::tableBulkActions());
    }

    /** @return array<Tables\Columns\Column> */
    public static function tableColumns(bool $withProvider = true): array
    {
        return array_values(array_filter([
            $withProvider ? Tables\Columns\TextColumn::make('provider.name')->label('مقدم الخدمة')->searchable()->sortable() : null,
            Tables\Columns\TextColumn::make('user.name')->label('المقيّم')->searchable()
                ->description(fn (Review $r) => $r->show_name ? null : '(مخفي علناً)'),
            Tables\Columns\TextColumn::make('rating')->label('التقييم')->sortable()
                ->formatStateUsing(fn ($state) => str_repeat('★', $state).' '.$state)
                ->color(fn ($state) => $state >= 4 ? 'success' : ($state >= 3 ? 'warning' : 'danger')),
            Tables\Columns\TextColumn::make('title')->label('العنوان')->limit(40)->searchable()
                ->tooltip(fn (Review $r) => $r->body),
            Tables\Columns\TextColumn::make('status')->label('الحالة')->badge()
                ->formatStateUsing(fn ($state) => Review::STATUSES[$state] ?? $state)
                ->description(fn (Review $r) => $r->status === Review::STATUS_PENDING && $r->hold_reason
                    ? 'السبب: '.(SignupGuard::HOLD_REASONS[$r->hold_reason] ?? $r->hold_reason) : null)
                ->color(fn ($state) => match ($state) {
                    Review::STATUS_APPROVED => 'success',
                    Review::STATUS_PENDING => 'warning',
                    default => 'danger',
                }),
            Tables\Columns\TextColumn::make('helpful_count')->label('مفيد')->sortable()->alignCenter(),
            Tables\Columns\TextColumn::make('open_reports_count')->label('بلاغات')->sortable()->alignCenter()
                ->badge()->color(fn ($state) => $state >= 3 ? 'danger' : ($state > 0 ? 'warning' : 'gray'))
                ->formatStateUsing(fn ($state) => $state ?: '—'),
            Tables\Columns\TextColumn::make('ip')->label('نفس IP')->alignCenter()->badge()
                ->state(fn (Review $r) => $r->ip ? static::sameIpQuery($r)->count() : 0)
                ->formatStateUsing(fn ($state) => $state ? '⚠️ '.$state : '—')
                ->color(fn ($state) => $state ? 'danger' : 'gray')
                ->tooltip(fn (Review $r) => $r->ip ? static::sameIpQuery($r)->with('user:id,name')->get()
                    ->map(fn (Review $o) => $o->user?->name ?? '—')->prepend("IP: {$r->ip} — تقييمات أخرى لنفس القناة:")->implode("\n") : null),
            Tables\Columns\TextColumn::make('created_at')->label('التاريخ')->since()->sortable(),
        ]));
    }

    /** Reviews of the same channel by other accounts from the same IP */
    public static function sameIpQuery(Review $review)
    {
        return Review::where('provider_id', $review->provider_id)->where('ip', $review->ip)->where('user_id', '!=', $review->user_id);
    }

    public static function tableActions(): array
    {
        return [
            Tables\Actions\Action::make('approve')->label('نشر')->icon('heroicon-o-check')->color('success')
                ->visible(fn (Review $r) => $r->status !== Review::STATUS_APPROVED)
                ->action(fn (Review $r) => $r->update(['status' => Review::STATUS_APPROVED])),
            Tables\Actions\Action::make('reject')->label('رفض')->icon('heroicon-o-x-mark')->color('danger')
                ->visible(fn (Review $r) => $r->status !== Review::STATUS_REJECTED)
                ->form([Forms\Components\Textarea::make('admin_note')->label('سبب الرفض (داخلي)')])
                ->action(fn (Review $r, array $data) => $r->update(['status' => Review::STATUS_REJECTED, 'admin_note' => $data['admin_note'] ?? $r->admin_note])),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ];
    }

    public static function tableBulkActions(): array
    {
        return [
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\BulkAction::make('approve')->label('نشر المحدد')->icon('heroicon-o-check')->color('success')
                    ->action(fn (Collection $records) => $records->each->update(['status' => Review::STATUS_APPROVED]))
                    ->deselectRecordsAfterCompletion(),
                Tables\Actions\BulkAction::make('reject')->label('رفض المحدد')->icon('heroicon-o-x-mark')->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each->update(['status' => Review::STATUS_REJECTED]))
                    ->deselectRecordsAfterCompletion(),
                Tables\Actions\DeleteBulkAction::make(),
            ]),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviews::route('/'),
            'edit' => Pages\EditReview::route('/{record}/edit'),
        ];
    }
}
