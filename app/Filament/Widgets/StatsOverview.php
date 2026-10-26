<?php

namespace App\Filament\Widgets;

use App\Models\Provider;
use App\Models\ProviderSubmission;
use App\Models\Review;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $reviewsPerDay = collect(range(13, 0))
            ->map(fn ($days) => Review::whereDate('created_at', now()->subDays($days))->count())
            ->all();

        return [
            Stat::make('مقدمو الخدمة', Provider::count())
                ->description(Provider::where('is_verified', true)->count().' موثّق')
                ->icon('heroicon-o-user-group'),
            Stat::make('التقييمات المنشورة', Review::where('status', Review::STATUS_APPROVED)->count())
                ->description('آخر 14 يوماً')
                ->chart($reviewsPerDay)
                ->color('success')
                ->icon('heroicon-o-star'),
            Stat::make('تقييمات بانتظار المراجعة', Review::where('status', Review::STATUS_PENDING)->count())
                ->color('warning')
                ->icon('heroicon-o-clock'),
            Stat::make('طلبات إضافة جديدة', ProviderSubmission::where('status', 'new')->count())
                ->color('info')
                ->icon('heroicon-o-inbox-arrow-down'),
            Stat::make('المستخدمون', User::count())
                ->icon('heroicon-o-users'),
            Stat::make('متوسط التقييم العام', number_format((float) Review::where('status', Review::STATUS_APPROVED)->avg('rating'), 2))
                ->icon('heroicon-o-chart-bar'),
        ];
    }
}
