<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A Google / Facebook / Telegram identity linked to a member account. */
class SocialAccount extends Model
{
    protected $fillable = [
        'provider', 'provider_id', 'email', 'name', 'nickname', 'avatar_url',
        'raw_data', 'token', 'refresh_token', 'token_expires_at', 'scopes', 'last_login_at',
    ];

    protected $hidden = ['token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
            'token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'scopes' => 'array',
            'last_login_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
