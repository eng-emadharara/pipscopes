<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImpersonatorReport extends Model
{
    protected $fillable = ['impersonator_id', 'user_id', 'voter_key', 'details', 'contact', 'ip_address'];

    protected static function booted(): void
    {
        static::created(fn (ImpersonatorReport $report) => $report->impersonator?->refreshReportsCount());
        static::deleted(fn (ImpersonatorReport $report) => $report->impersonator?->refreshReportsCount());
    }

    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(Impersonator::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
