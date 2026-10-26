<?php

namespace App\Support;

use App\Models\Provider;
use App\Models\ProviderOwner;
use App\Models\Setting;

/**
 * Channel owners manage their channel straight from its public page: replies under each
 * review and an "edit details" window. This class answers "is someone managing this page?".
 */
class OwnerPortal
{
    public static function enabled(): bool
    {
        return (bool) Setting::get('owner_portal_enabled', false);
    }

    /** The signed-in owner, if the portal is on and their account and channel are active. */
    public static function owner(): ?ProviderOwner
    {
        if (! static::enabled()) {
            return null;
        }

        $owner = auth('owner')->user();

        return $owner instanceof ProviderOwner && $owner->canSignIn() ? $owner : null;
    }

    /** Whether the current visitor is an owner of this provider (owner mode on its page). */
    public static function manages(Provider $provider): bool
    {
        return static::owner()?->provider_id === $provider->id;
    }
}
