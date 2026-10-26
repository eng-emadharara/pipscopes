<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewReport extends Model
{
    /** Reason keys; labels are in lang/{locale}/trust.php under report_reasons. */
    public const REASONS = ['fake', 'abusive', 'personal_info', 'spam', 'other'];

    public const STATUSES = [
        'open' => 'بانتظار القرار',
        'resolved' => 'تم إخفاء التقييم',
        'dismissed' => 'مرفوض (التقييم سليم)',
    ];

    protected $fillable = ['review_id', 'user_id', 'voter_key', 'reason', 'details', 'status', 'admin_note'];

    protected static function booted(): void
    {
        static::saved(fn (ReviewReport $report) => $report->review?->refreshCounters());
        static::deleted(fn (ReviewReport $report) => $report->review?->refreshCounters());
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
