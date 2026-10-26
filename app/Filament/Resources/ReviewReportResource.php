<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewReportResource\Pages;
use App\Models\Review;
use App\Models\ReviewReport;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReviewReportResource extends Resource
{
    protected static ?string $model = ReviewReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationGroup = 'الطلبات والتقييمات';

    protected static ?string $modelLabel = 'بلاغ';

    protected static ?string $pluralModelLabel = 'بلاغات التقييمات';

    public static function getNavigationBadge(): ?string
    {
        $open = ReviewReport::where('status', 'open')->count();

        return $open ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function reasonLabel(string $reason): string
    {
        return trans("trust.report_reasons.$reason", [], 'ar');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['review.provider', 'review.user', 'user']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('review.title')->label('التقييم')->wrap()->limit(60)
                    ->description(fn (ReviewReport $r) => $r->review?->provider?->name.' · '.str_repeat('★', (int) $r->review?->rating))
                    ->tooltip(fn (ReviewReport $r) => $r->review?->body)
                    ->url(fn (ReviewReport $r) => $r->review?->provider ? route('providers.show', $r->review->provider).'#reviews' : null, shouldOpenInNewTab: true),
                Tables\Columns\TextColumn::make('reason')->label('السبب')->badge()
                    ->formatStateUsing(fn ($state) => static::reasonLabel($state))
                    ->color(fn ($state) => in_array($state, ['fake', 'abusive']) ? 'danger' : 'gray')
                    ->description(fn (ReviewReport $r) => $r->details ? \Illuminate\Support\Str::limit($r->details, 80) : null),
                Tables\Columns\TextColumn::make('review.open_reports_count')->label('بلاغات مفتوحة على التقييم')
                    ->badge()->color(fn ($state) => $state >= 3 ? 'danger' : 'warning'),
                Tables\Columns\TextColumn::make('reporter')->label('المُبلّغ')
                    ->state(fn (ReviewReport $r) => $r->user?->name ?? 'زائر'),
                Tables\Columns\TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn ($state) => ReviewReport::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'open' => 'warning',
                        'resolved' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')->label('التاريخ')->since()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('الحالة')->options(ReviewReport::STATUSES)->default('open'),
                Tables\Filters\SelectFilter::make('reason')->label('السبب')
                    ->options(collect(ReviewReport::REASONS)->mapWithKeys(fn ($r) => [$r => static::reasonLabel($r)])),
            ])
            ->actions([
                Tables\Actions\Action::make('hide_review')->label('إخفاء التقييم')->icon('heroicon-o-eye-slash')->color('danger')
                    ->visible(fn (ReviewReport $r) => $r->review?->status === Review::STATUS_APPROVED)
                    ->requiresConfirmation()
                    ->modalDescription('سيتم رفض التقييم (يختفي من الموقع ويُعاد حساب تقييم مقدم الخدمة) وإغلاق كل البلاغات المفتوحة عليه.')
                    ->form([Forms\Components\Textarea::make('admin_note')->label('ملاحظة داخلية (اختياري)')])
                    ->action(function (ReviewReport $record, array $data) {
                        $review = $record->review;
                        $review->update([
                            'status' => Review::STATUS_REJECTED,
                            'admin_note' => trim(($review->admin_note ? $review->admin_note."\n" : '').'أُخفي بسبب بلاغ: '.static::reasonLabel($record->reason).($data['admin_note'] ? ' - '.$data['admin_note'] : '')),
                        ]);
                        $review->reports()->where('status', 'open')->get()
                            ->each->update(['status' => 'resolved', 'admin_note' => $data['admin_note'] ?: null]);

                        Notification::make()->title('تم إخفاء التقييم وإغلاق بلاغاته')->success()->send();
                    }),
                Tables\Actions\Action::make('dismiss')->label('التقييم سليم')->icon('heroicon-o-check')->color('gray')
                    ->visible(fn (ReviewReport $r) => $r->status === 'open')
                    ->requiresConfirmation()
                    ->modalDescription('سيبقى التقييم منشوراً، وتُغلق كل البلاغات المفتوحة عليه.')
                    ->action(function (ReviewReport $record) {
                        $record->review->reports()->where('status', 'open')->get()->each->update(['status' => 'dismissed']);
                        Notification::make()->title('تم إغلاق البلاغات وإبقاء التقييم')->success()->send();
                    }),
                Tables\Actions\Action::make('edit_review')->label('فتح التقييم')->icon('heroicon-o-pencil-square')
                    ->url(fn (ReviewReport $r) => ReviewResource::getUrl('edit', ['record' => $r->review_id])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviewReports::route('/'),
        ];
    }
}
