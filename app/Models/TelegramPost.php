<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One post of a channel as its public preview showed it: numbers only (App\Services\TelegramPosts). */
class TelegramPost extends Model
{
    public $timestamps = false;

    protected $fillable = ['provider_id', 'msg_id', 'posted_at', 'views', 'edited', 'kind', 'brokers', 'first_seen_at', 'deleted_at'];

    protected $casts = [
        'msg_id' => 'integer',
        'views' => 'integer',
        'edited' => 'boolean',
        'posted_at' => 'datetime',
        'first_seen_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
