<?php

namespace App\Models;

use App\Notifications\ScamReportReviewed;
use App\Support\ScamHelp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A member's "I was scammed" report. Its proofs stay private (local disk, seen only by the team);
 * once confirmed it counts in its case (ScamCase), whose updates reach the member.
 */
class ScamReport extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING => 'بانتظار المراجعة',
        self::STATUS_CONFIRMED => 'مؤكد',
        self::STATUS_REJECTED => 'مرفوض',
    ];

    /** Where proofs are kept: not the public disk */
    public const DISK = 'local';

    public const MAX_PROOFS = 5;

    protected $fillable = [
        'user_id', 'scam_case_id', 'scam_type', 'payment_method', 'paid_at', 'amount', 'currency', 'amount_usd',
        'account', 'wallet', 'broker', 'story', 'proofs', 'contact', 'consent_share', 'consent_contact',
        'status', 'admin_note', 'ip',
    ];

    protected $casts = [
        'paid_at' => 'date',
        'amount' => 'decimal:2',
        'amount_usd' => 'decimal:2',
        'proofs' => 'array',
        'consent_share' => 'boolean',
        'consent_contact' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (ScamReport $report) {
            if ($report->isDirty(['amount', 'currency'])) {
                $report->amount_usd = ScamHelp::toUsd($report->amount !== null ? (float) $report->amount : null, $report->currency);
            }
        });

        static::saved(function (ScamReport $report) {
            if ($report->wasChanged(['status', 'scam_case_id', 'amount_usd']) || $report->wasRecentlyCreated) {
                $report->scamCase?->recount();
                if ($report->wasChanged('scam_case_id') && $report->getOriginal('scam_case_id')) {
                    ScamCase::find($report->getOriginal('scam_case_id'))?->recount();
                }
            }
        });

        // The member hears once their report is looked at
        static::updated(function (ScamReport $report) {
            if ($report->wasChanged('status') && $report->status !== self::STATUS_PENDING) {
                $report->user?->notify(new ScamReportReviewed($report));
            }
        });

        static::deleted(function (ScamReport $report) {
            $report->scamCase?->recount();
            foreach ($report->proofs ?? [] as $path) {
                Storage::disk(self::DISK)->delete($path);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scamCase(): BelongsTo
    {
        return $this->belongsTo(ScamCase::class);
    }

    public function typeLabel(): string
    {
        return ScamHelp::types()[$this->scam_type][1] ?? $this->scam_type;
    }

    public function paymentLabel(): string
    {
        return ScamHelp::payments()[$this->payment_method] ?? $this->payment_method;
    }

    public function amountLabel(): ?string
    {
        return $this->amount !== null ? number_format((float) $this->amount, (float) $this->amount == (int) $this->amount ? 0 : 2).' '.$this->currency : null;
    }
}
