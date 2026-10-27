<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One channel's Telegram numbers on a day (or averaged over a week / month once compacted). */
class TelegramSnapshot extends Model
{
    public const DAY = 'd';

    public const WEEK = 'w';

    public const MONTH = 'm';

    public $timestamps = false;

    protected $fillable = ['provider_id', 'date', 'period', 'subscribers', 'avg_views'];

    // 'date' stays a Y-m-d string: a date cast would store a time too and break the unique key
    protected $casts = [
        'subscribers' => 'integer',
        'avg_views' => 'integer',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
