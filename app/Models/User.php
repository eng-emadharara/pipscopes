<?php

namespace App\Models;

use Database\Factories\UserFactory;
use App\Support\SignupGuard;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Solutionforest\FilamentEmail2fa\Exceptions\InvalidTwoFACodeException;
use Solutionforest\FilamentEmail2fa\Interfaces\RequireTwoFALogin;
use Solutionforest\FilamentEmail2fa\Mail\TwoFAEmail;
use Solutionforest\FilamentEmail2fa\Trait\HasTwoFALogin;

class User extends Authenticatable implements FilamentUser, HasLocalePreference, RequireTwoFALogin
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasTwoFALogin, Notifiable;

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

    protected static function booted(): void
    {
        // Kept in step with the email so one inbox can't sign up again as a.b@gmail.com or ab+2@gmail.com
        static::saving(function (User $user) {
            if ($user->isDirty('email')) {
                $user->email_canonical = SignupGuard::canonical($user->email);
            }
        });
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

    /**
     * The admin panel's email code (HasTwoFALogin). Replaces the package's version, which used mt_rand
     * and stored the code as typed: here it comes from random_int and only its HMAC is kept.
     */
    public function generate2FACode(): string
    {
        $this->twoFaCodes()->delete();
        $code = sprintf('%06d', random_int(0, 999999));
        $this->twoFaCodes()->create([
            'code' => self::hash2FACode($code),
            'expiry_at' => now()->addMinutes((int) config('filament-email-2fa.expiry_time_by_mins', 10)),
        ]);

        return $code;
    }

    /** Filament's login response sends this on every sign-in, so it's skipped when config trust.admin_email_2fa is off. */
    public function send2FAEmail()
    {
        if (config('trust.admin_email_2fa') && $this->email) {
            Mail::to(trim($this->email))->send(new TwoFAEmail($this->name, $this->generate2FACode()));
        }
    }

    /** Whether this session entered a correct code (HasTwoFALogin); always yes when config trust.admin_email_2fa is off. */
    public function isTwoFaVerfied(?string $session_id = null): bool
    {
        if (! config('trust.admin_email_2fa')) {
            return true;
        }

        return $this->twoFaVerifis()->where('session_id', $session_id ?? session()->getId())->exists();
    }

    /** A correct code works once; App\Filament\Auth\EmailTwoFactor limits the wrong ones. */
    public function verify2FACode(string $code)
    {
        // Queried, not the loaded relation: it may have been read before the latest code was sent
        $stored = $this->latest_2fa_code()->first()?->code;

        if ($stored === null || ! hash_equals($stored, self::hash2FACode(trim($code)))) {
            throw new InvalidTwoFACodeException;
        }

        $this->twoFaCodes()->delete();
    }

    protected static function hash2FACode(string $code): string
    {
        return hash_hmac('sha256', $code, config('app.key'));
    }
}
