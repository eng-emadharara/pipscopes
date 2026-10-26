<?php

namespace App\Support;

use App\Models\Impersonator;
use App\Models\Provider;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * "Is this account real?": looks up an account a visitor was contacted by (a link, "@name"
 * or a phone number) against the providers' official accounts and the confirmed impersonators.
 */
class AccountCheck
{
    /**
     * @return array{status: 'invalid'|'official'|'impersonator'|'unknown', query?: array, official?: Collection, impersonators?: Collection}
     */
    public static function run(string $input): array
    {
        $query = self::parse($input);
        if (! $query) {
            return ['status' => 'invalid'];
        }

        $keys = $query['keys'];

        // Active providers with one of the searched keys among their official accounts
        $official = Provider::active()->get()
            ->map(fn (Provider $provider) => [
                'provider' => $provider,
                'platforms' => collect($provider->officialAccountKeys())->intersect($keys)
                    ->map(fn (string $key) => Str::before($key, ':'))->values()->all(),
            ])
            ->filter(fn (array $match) => $match['platforms'])
            ->values();

        $impersonators = Impersonator::confirmed()
            ->whereIn('account_key', $keys)
            ->whereHas('provider', fn ($q) => $q->active())
            ->with('provider')
            ->limit(5)
            ->get();

        return [
            // A known scam account wins even when the same name is official on another platform
            'status' => $impersonators->isNotEmpty() ? 'impersonator' : ($official->isNotEmpty() ? 'official' : 'unknown'),
            'query' => $query,
            'official' => $official,
            'impersonators' => $impersonators,
        ];
    }

    /**
     * The keys to look up. A full link names one platform; a bare "@name" could be on any of
     * them (we don't know where the visitor was contacted), so it's checked on all.
     *
     * @return array{display: string, platform: ?string, keys: list<string>}|null
     */
    public static function parse(string $input): ?array
    {
        $input = trim($input);

        // Phone numbers: WhatsApp
        if (preg_match('/^\+?[\d\s\-()]{7,}$/', $input)) {
            $parsed = AccountHandle::parse($input, 'whatsapp');

            return $parsed ? ['display' => $parsed['display'], 'platform' => 'whatsapp', 'keys' => [$parsed['key']]] : null;
        }

        $isLink = (bool) preg_match('#^(https?://|www\.)#i', $input) || str_contains($input, '/');
        $parsed = AccountHandle::parse($input, $isLink ? null : 'telegram');
        if (! $parsed || preg_match('/\s/', $input) || mb_strlen(Str::after($parsed['key'], ':')) < 3) {
            return null;
        }

        if ($isLink) {
            return ['display' => $parsed['display'], 'platform' => $parsed['platform'], 'keys' => [$parsed['key']]];
        }

        $name = Str::after($parsed['key'], ':');
        $keys = array_map(fn (string $platform) => "$platform:$name", AccountHandle::USERNAME_PLATFORMS);

        // "goldpro.com" may be a website as much as a dotted username
        if (preg_match('#^[a-z0-9-]+(\.[a-z0-9-]+)*\.[a-z]{2,}$#', $name)) {
            $keys[] = "website:$name";
        }

        return ['display' => '@'.$name, 'platform' => null, 'keys' => $keys];
    }
}
