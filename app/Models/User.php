<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class User extends Authenticatable implements FilamentUser, HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'email_verified_at',
        'avatar_url',
        'social_links',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'verification_code',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'verification_code_expires_at' => 'datetime',
            'verification_code_sent_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'social_links' => 'array',
            'notification_prefs' => 'array',
            'telegram_blocked_at' => 'datetime',
        ];
    }

    /** Notifications go out in the language the member last used on the site (App\Http\Middleware\SetLocale) */
    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Whether the member has proved who they are: a confirmed email, or a Telegram sign-in
     * (Telegram signs the identity itself, so those members don't need an email at all).
     * Account linking still goes by hasVerifiedEmail(), since only that proves the address.
     */
    public function isVerifiedMember(): bool
    {
        return $this->hasVerifiedEmail() || $this->socialAccounts->contains('provider', 'telegram');
    }

    /** A photo picked up from Google/Telegram is a full URL; one uploaded on the profile page is a path on the public disk. */
    public function avatarUrl(): ?string
    {
        if (! $this->avatar_url) {
            return null;
        }

        return Str::startsWith($this->avatar_url, ['http://', 'https://'])
            ? $this->avatar_url
            : Storage::disk('public')->url($this->avatar_url);
    }

    public function hasUploadedAvatar(): bool
    {
        return $this->avatar_url && ! Str::startsWith($this->avatar_url, ['http://', 'https://']);
    }

    public function initials(): string
    {
        return Str::upper(mb_substr(trim((string) $this->name), 0, 1)) ?: '?';
    }

    /** @return array<string, array{label:string, icon:string, url:string}> */
    public function socialLinks(): array
    {
        $links = [];
        foreach (config('trust.socials') as $field => $icon) {
            if ($url = $this->social_links[$field] ?? null) {
                $links[$field] = ['label' => __("trust.socials.$field"), 'icon' => $icon, 'url' => $url];
            }
        }

        return $links;
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Cast: a user created in memory has no is_admin value until it is reloaded from the database
        return (bool) $this->is_admin;
    }
}
