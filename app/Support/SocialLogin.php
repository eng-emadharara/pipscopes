<?php

namespace App\Support;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Signing in with Google, Facebook and Telegram: which ones are on, and which account an identity belongs to. */
class SocialLogin
{
    public const LABELS = [
        'google' => 'Google',
        'facebook' => 'Facebook',
        'telegram' => 'Telegram',
    ];

    /** Providers that use the OAuth redirect flow (Telegram signs in through its own widget). */
    public const OAUTH = ['google', 'facebook'];

    public static function isEnabled(string $provider): bool
    {
        return match ($provider) {
            'google', 'facebook' => filled(config("services.$provider.client_id")) && filled(config("services.$provider.client_secret")),
            'telegram' => TelegramLogin::botId() !== null,
            default => false,
        };
    }

    /** @return list<string> */
    public static function enabled(): array
    {
        return array_values(array_filter(array_keys(self::LABELS), self::isEnabled(...)));
    }

    /**
     * Finds or creates the member account for a provider identity.
     * Returns null when the email belongs to an existing account but the provider hasn't verified it,
     * so linking would let anyone claim that account.
     *
     * @param  array{id: string, name: ?string, email: ?string, email_verified: bool, avatar: ?string, nickname: ?string}  $profile
     */
    public static function resolveUser(string $provider, array $profile): ?User
    {
        return DB::transaction(function () use ($provider, $profile) {
            $email = filled($profile['email']) ? Str::lower(trim($profile['email'])) : null;
            $details = [
                'email' => $email,
                'nickname' => $profile['nickname'],
                'avatar_url' => $profile['avatar'],
                'last_login_at' => now(),
            ];

            $account = SocialAccount::where('provider', $provider)->where('provider_id', $profile['id'])->first();
            if ($account) {
                $account->update($details);

                return $account->user;
            }

            $user = $email ? User::where('email', $email)->first() : null;

            if ($user && ! $profile['email_verified']) {
                return null;
            }

            if ($user) {
                if (! $user->hasVerifiedEmail()) {
                    // Whoever registered this address never proved they own it; the provider just did.
                    // Drop their password and sessions so a pre-registered account can't be used to spy on the owner.
                    $user->forceFill([
                        'password' => null,
                        'remember_token' => null,
                        'email_verified_at' => now(),
                        'verification_code' => null,
                        'verification_code_expires_at' => null,
                    ])->save();

                    if (config('session.driver') === 'database') {
                        DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
                    }
                }
            } else {
                $user = new User([
                    'name' => self::displayName($profile, $email),
                    'email' => $email,
                ]);
                $user->email_verified_at = $email && $profile['email_verified'] ? now() : null;
                $user->save();
            }

            if (! $user->avatar_url && $profile['avatar']) {
                $user->forceFill(['avatar_url' => $profile['avatar']])->save();
            }

            // One identity per provider: a second Google account replaces the first rather than piling up
            $user->socialAccounts()->updateOrCreate(['provider' => $provider], ['provider_id' => $profile['id'], ...$details]);

            return $user;
        });
    }

    /** @param  array{name: ?string, nickname: ?string}  $profile */
    protected static function displayName(array $profile, ?string $email): string
    {
        $name = trim((string) ($profile['name'] ?: $profile['nickname'] ?: ($email ? Str::before($email, '@') : '')));

        return Str::limit($name !== '' ? $name : __('عضو جديد'), 100, '');
    }
}
