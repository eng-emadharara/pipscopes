<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A channel owner's login (guard "owner"). Accounts are created by admins; the owner signs in
 * on the site and manages their channel from its public page (see App\Support\OwnerPortal).
 */
class ProviderOwner extends Authenticatable
{
    protected $fillable = ['provider_id', 'name', 'email', 'password', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    // Matches the column default, so a freshly created account is active before it's reloaded
    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(ProviderChange::class);
    }

    /** Active account for a channel that is visible on the site. */
    public function canSignIn(): bool
    {
        return $this->is_active && (bool) $this->provider?->is_active;
    }
}
