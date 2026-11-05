<?php

namespace App\Support;

/** Inviting visitors to review a channel they tried: the hero's buttons and a small card a little after arriving. */
class ReviewInvite
{
    /** Pages with their own job to finish, or where the invitation is already on the page */
    protected const QUIET_ROUTES = [
        'login', 'register', 'verification.*', 'reviews.*', 'submissions.*', 'providers.show',
        'profile.*', 'notifications.*', 'owner.*', 'contact*',
    ];

    /** Visitors can open the channels and write; channel owners never review */
    public static function available(): bool
    {
        return ! Launch::appliesToVisitor() && ! OwnerPortal::owner();
    }

    /** The card is for people who haven't reviewed anything yet */
    public static function showCard(): bool
    {
        return self::available()
            && ! request()->routeIs(...self::QUIET_ROUTES)
            && ! auth()->user()?->reviews()->exists();
    }
}
