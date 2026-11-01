<?php

namespace App\Support;

use App\Mail\EarlyReviewAlertMail;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Early-open channels publish reviews straight away, with no moderation (App\Support\Launch::earlyOpen),
 * so anything that looks organised is flagged on the review and emailed to the admins. It never stops a review.
 */
class EarlyReviewAlerts
{
    public const REASONS = [
        'burst' => 'موجة تقييمات خلال ساعة',
        'duplicate' => 'نص مطابق لتقييم آخر',
        'same_ip' => 'حساب آخر من نفس IP',
    ];

    /** One email per channel and reason in this time, however many reviews trigger it */
    protected const EMAIL_EVERY_MINUTES = 60;

    public static function check(Review $review): void
    {
        $reasons = self::reasons($review);
        $review->forceFill(['alerts' => $reasons ?: null])->saveQuietly();

        $fresh = array_values(array_filter($reasons, fn (string $reason) => Cache::add(
            "early-alert:{$review->provider_id}:$reason", true, now()->addMinutes(self::EMAIL_EVERY_MINUTES),
        )));

        if ($fresh) {
            self::email($review, $fresh);
        }
    }

    /** @return list<string> keys of REASONS */
    protected static function reasons(Review $review): array
    {
        $others = Review::where('provider_id', $review->provider_id)->whereKeyNot($review->id);
        $text = fn (?string $body) => Str::lower(Str::squish((string) $body));

        return array_keys(array_filter([
            'burst' => Review::where('provider_id', $review->provider_id)->where('created_at', '>=', now()->subHour())->count()
                >= (int) SignupGuard::setting('early_alert_burst'),
            'duplicate' => (clone $others)->latest('id')->take(500)->pluck('body')->contains(fn ($body) => $text($body) === $text($review->body)),
            'same_ip' => $review->ip && (clone $others)->where('ip', $review->ip)->where('user_id', '!=', $review->user_id)->exists(),
        ]));
    }

    /** @param  list<string>  $reasons */
    protected static function email(Review $review, array $reasons): void
    {
        $admins = User::where('is_admin', true)->whereNotNull('email')->whereNotNull('email_verified_at')->get();

        foreach ($admins as $admin) {
            try {
                Mail::to($admin)->locale('ar')->queue(new EarlyReviewAlertMail($review, $reasons));
            } catch (Throwable $e) {
                // The member's review is saved; a mail problem must not fail their request
                Log::error('Early review alert failed', ['review_id' => $review->id, 'error' => $e->getMessage()]);
            }
        }
    }
}
