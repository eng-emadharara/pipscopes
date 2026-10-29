<?php

namespace App\Support;

use App\Models\Provider;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Keeps one person from opening account after account to post reviews: throwaway inboxes and fake domains,
 * the same inbox under another spelling (Gmail dots, +tags), many sign-ups from one IP, and a hold on the
 * first reviews of brand-new accounts. Each layer is switched in Admin → الإعدادات ← حماية التسجيل.
 */
class SignupGuard
{
    public const SETTING = 'signup_protection';

    public const DEFAULTS = [
        'block_disposable' => true,
        'check_mx' => true,
        'block_aliases' => true,
        'ip_daily_limit' => 3,
        'hold_new_reviews' => true,
        'new_account_days' => 3,
        'hold_same_ip' => true,
        'blocked_domains' => '',
        'allowed_domains' => '',
    ];

    /** Community-kept list of throwaway email domains, refreshed weekly by `signup:update-disposable` */
    public const LIST_URL = 'https://raw.githubusercontent.com/disposable-email-domains/disposable-email-domains/master/disposable_email_blocklist.conf';

    public const HOLD_REASONS = [
        'new_account' => 'حساب جديد',
        'same_ip' => 'نفس IP لتقييم آخر على القناة',
    ];

    /** Providers whose inbox ignores dots in the name part */
    protected const DOTLESS = ['gmail.com' => 'gmail.com', 'googlemail.com' => 'gmail.com'];

    /** @var array<string, true>|null */
    protected static ?array $disposable = null;

    protected static ?Closure $mxResolver = null;

    public static function setting(string $key): mixed
    {
        return data_get(Setting::get(self::SETTING), $key) ?? self::DEFAULTS[$key];
    }

    public static function listPath(): string
    {
        return storage_path('app/disposable-domains.txt');
    }

    /** The downloaded list, or the short one shipped with the site until the first download */
    public static function disposableDomains(): array
    {
        if (self::$disposable === null) {
            $path = File::exists(self::listPath()) ? self::listPath() : resource_path('data/disposable-domains.txt');
            self::$disposable = array_fill_keys(self::parseDomains(File::get($path)), true);
        }

        return self::$disposable;
    }

    /** @return list<string> */
    public static function parseDomains(?string $text): array
    {
        return collect(preg_split('/[\s,;]+/', Str::lower((string) $text)))
            ->map(fn ($d) => trim(ltrim($d, '@.')))
            ->filter(fn ($d) => $d !== '' && ! str_starts_with($d, '#') && str_contains($d, '.'))
            ->unique()->values()->all();
    }

    /** Matches the domain and its parents, so sub.mailinator.com counts as mailinator.com */
    protected static function matches(string $domain, array $set): bool
    {
        $parts = explode('.', $domain);
        while (count($parts) >= 2) {
            if (isset($set[implode('.', $parts)])) {
                return true;
            }
            array_shift($parts);
        }

        return false;
    }

    public static function domain(string $email): string
    {
        return Str::lower(Str::afterLast(trim($email), '@'));
    }

    public static function isDisposable(string $email): bool
    {
        return self::matches(self::domain($email), self::disposableDomains());
    }

    /** Why this address can't be used to sign up, or null when it's fine */
    public static function emailProblem(string $email): ?string
    {
        $domain = self::domain($email);

        if (self::matches($domain, array_fill_keys(self::parseDomains(self::setting('allowed_domains')), true))) {
            return null;
        }

        if (self::matches($domain, array_fill_keys(self::parseDomains(self::setting('blocked_domains')), true))
            || (self::setting('block_disposable') && self::isDisposable($email))) {
            return __('لا نقبل عناوين البريد المؤقتة. استخدم بريدك الدائم (مثل Gmail أو Outlook).');
        }

        if (self::setting('check_mx') && ! self::hasMailServer($domain)) {
            return __('نطاق هذا البريد لا يستقبل رسائل. تأكد من كتابته بشكل صحيح.');
        }

        return null;
    }

    /** Forgets the domain list loaded in this process */
    public static function flush(): void
    {
        self::$disposable = null;
    }

    /** Replaces the DNS lookup, for tests */
    public static function resolveMxUsing(?Closure $resolver): void
    {
        self::$mxResolver = $resolver;
    }

    public static function hasMailServer(string $domain): bool
    {
        if (self::$mxResolver) {
            return (bool) (self::$mxResolver)($domain);
        }

        return Cache::remember("signup-mx:$domain", now()->addDay(), function () use ($domain) {
            if (! function_exists('dns_get_record')) {
                return true;
            }

            foreach ([DNS_MX, DNS_A, DNS_AAAA] as $type) {
                $records = @dns_get_record($domain.'.', $type);
                if ($records === false) {
                    return true; // The lookup itself failed: never lock everyone out over a DNS hiccup
                }
                if ($records) {
                    return true;
                }
            }

            return false;
        });
    }

    /** The inbox an address really lands in: lower case, no +tag, and no dots for Gmail */
    public static function canonical(?string $email): ?string
    {
        if (! $email || ! str_contains($email, '@')) {
            return $email ? Str::lower(trim($email)) : null;
        }

        $domain = self::domain($email);
        $local = Str::before(Str::lower(Str::beforeLast(trim($email), '@')), '+');

        if (isset(self::DOTLESS[$domain])) {
            $local = str_replace('.', '', $local);
            $domain = self::DOTLESS[$domain];
        }

        return "$local@$domain";
    }

    /** Another account already uses the same inbox under a different spelling */
    public static function aliasTaken(string $email, ?int $ignoreUserId = null): bool
    {
        return self::setting('block_aliases') && User::where('email_canonical', self::canonical($email))
            ->when($ignoreUserId, fn ($q) => $q->whereKeyNot($ignoreUserId))->exists();
    }

    public static function ipLimitReached(?string $ip): bool
    {
        $limit = (int) self::setting('ip_daily_limit');

        return $ip && $limit > 0 && User::where('signup_ip', $ip)->where('created_at', '>=', now()->subDay())->count() >= $limit;
    }

    /** Why a review that would publish straight away should wait for an admin instead */
    public static function holdReason(User $user, Provider $provider, ?string $ip): ?string
    {
        if ($user->is_admin) {
            return null;
        }

        if (self::setting('hold_same_ip') && $ip && Review::where('provider_id', $provider->id)
            ->where('ip', $ip)->where('user_id', '!=', $user->id)->exists()) {
            return 'same_ip';
        }

        $days = (int) self::setting('new_account_days');
        if (self::setting('hold_new_reviews') && $days > 0 && $user->created_at?->gt(now()->subDays($days))
            && ! $user->reviews()->where('provider_id', '!=', $provider->id)->where('status', Review::STATUS_APPROVED)->exists()) {
            return 'new_account';
        }

        return null;
    }

    /** Downloads the latest throwaway-domain list. Returns how many domains it holds. */
    public static function updateDisposableList(): int
    {
        $domains = self::parseDomains(Http::timeout(30)->retry(2, 1000)->get(self::LIST_URL)->throw()->body());

        // A truncated or wrong download must not replace a good list
        throw_if(count($domains) < 1000, \RuntimeException::class, 'The downloaded list looks incomplete ('.count($domains).' domains).');

        File::ensureDirectoryExists(dirname(self::listPath()));
        File::put(self::listPath(), implode("\n", $domains)."\n");
        self::$disposable = null;

        return count($domains);
    }
}
