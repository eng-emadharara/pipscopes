<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A Google / Facebook / Telegram identity linked to a member account. */
class SocialAccount extends Model
{
    protected $fillable = ['provider', 'provider_id', 'email', 'nickname', 'avatar_url', 'last_login_at'];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
