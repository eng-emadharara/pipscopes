<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A bot chat following a channel (App\Support\ChannelWatch) */
class ChannelWatch extends Model
{
    protected $fillable = ['chat_id', 'provider_id'];

    protected $casts = ['chat_id' => 'integer'];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
