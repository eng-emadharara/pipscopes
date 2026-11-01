<?php

namespace App\Support;

use App\Filament\Resources\ContactMessageResource;
use App\Filament\Resources\ImpersonatorResource;
use App\Filament\Resources\ProviderSubmissionResource;
use App\Filament\Resources\ReviewReportResource;
use App\Filament\Resources\ReviewResource;
use App\Jobs\SendAdminAlert;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Impersonator;
use App\Models\ImpersonatorReport;
use App\Models\Market;
use App\Models\ProviderSubmission;
use App\Models\Review;
use App\Models\ReviewReport;
use Illuminate\Support\Str;
use Throwable;

/**
 * Telegram messages to the team when visitors send something that needs a look: a channel to add,
 * a review, a report on a review, an impersonating account, a contact message.
 * Sent by the login bot to the chats in TELEGRAM_ADMIN_CHAT_IDS; the texts are fixed here.
 */
class AdminAlerts
{
    protected const LINE = '━━━━━━━━━━━━━━━';

    /** @return list<string> From TELEGRAM_ADMIN_CHAT_IDS, comma separated: a member, a group (-100…) or a channel (@name) */
    public static function chatIds(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', (string) config('services.telegram.admin_chat_ids')))));
    }

    public static function enabled(): bool
    {
        return filled(config('services.telegram.bot_token')) && self::chatIds();
    }

    public static function submission(ProviderSubmission $submission): void
    {
        $username = $submission->telegram_data['username'] ?? null;

        self::send(fn () => self::message(
            '📥', 'طلب إضافة قناة جديد',
            [
                '📢 القناة' => self::bold($submission->name),
                '🔗 الرابط' => self::e($username ? '@'.$username : $submission->telegram_url),
                '👥 المشتركون' => isset($submission->telegram_data['subscribers']) ? number_format($submission->telegram_data['subscribers']) : null,
                '🏷 التصنيف' => self::e(Category::whereIn('id', $submission->category_ids ?? [])->orderBy('sort_order')->pluck('name')->implode('، ')),
                '🌍 الأسواق' => self::e(Market::whereIn('id', $submission->market_ids ?? [])->pluck('name')->implode('، ')),
            ],
            self::quote($submission->description, 300),
            '#طلب_إضافة',
            ['🗂 مراجعة الطلب', ProviderSubmissionResource::getUrl('edit', ['record' => $submission])],
        ));
    }

    public static function review(Review $review): void
    {
        $pending = $review->status === Review::STATUS_PENDING;

        self::send(fn () => self::message(
            '⭐️', 'تقييم جديد',
            [
                '📢 القناة' => self::bold($review->provider?->name),
                '🌟 التقييم' => self::stars($review->rating),
                '👤 الكاتب' => self::e($review->user?->name).($review->show_name ? '' : ' <i>(باسم مخفي)</i>'),
                '📌 الحالة' => $pending
                    ? '⏳ بانتظار المراجعة'.($review->hold_reason ? ' — '.self::e(SignupGuard::HOLD_REASONS[$review->hold_reason] ?? $review->hold_reason) : '')
                    : '✅ منشور',
            ],
            self::quote($review->body, 500),
            $pending ? '#تقييم #بانتظار_المراجعة' : '#تقييم',
            [$pending ? '🛡 مراجعة التقييم' : '📝 فتح التقييم', ReviewResource::getUrl('edit', ['record' => $review])],
        ));
    }

    public static function reviewReport(ReviewReport $report): void
    {
        $review = $report->review;

        self::send(fn () => self::message(
            '🚩', 'بلاغ عن تقييم',
            [
                '⚠️ السبب' => self::bold(__('trust.report_reasons.'.$report->reason, [], 'ar')),
                '📢 القناة' => self::e($review?->provider?->name),
                '🌟 التقييم المُبلَّغ عنه' => $review ? self::stars($review->rating).' — '.self::e($review->user?->name) : null,
                '🙋 المُبلِّغ' => self::reporter($report->user?->name, $report->voter_key),
                '📊 بلاغات مفتوحة عليه' => $review ? (string) $review->reports()->where('status', 'open')->count() : null,
            ],
            self::quote($report->details, 400, 'تفاصيل البلاغ').($review ? "\n\n".self::quote($review->body, 250, 'نص التقييم') : ''),
            '#بلاغ_تقييم',
            ['⚖️ اتخاذ قرار', ReviewReportResource::getUrl('index')],
        ));
    }

    public static function impersonatorReport(ImpersonatorReport $report): void
    {
        $impersonator = $report->impersonator;
        $first = $impersonator && $impersonator->reports_count <= 1;

        self::send(fn () => self::message(
            '🎭', $first ? 'بلاغ عن حساب منتحل' : 'بلاغ إضافي عن حساب منتحل',
            [
                '🎯 ينتحل صفة' => self::bold($impersonator?->provider?->name),
                '📱 المنصة' => self::e($impersonator?->platformLabel()),
                '🔗 الحساب' => '<code>'.self::e($impersonator?->handle).'</code>',
                '📊 عدد البلاغات' => $impersonator ? (string) $impersonator->reports_count : null,
                '📌 الحالة' => $impersonator ? self::e(Impersonator::STATUSES[$impersonator->status] ?? $impersonator->status) : null,
                '🙋 المُبلِّغ' => self::reporter($report->user?->name, $report->voter_key),
                '📞 للتواصل' => self::e($report->contact),
            ],
            self::quote($report->details, 500, 'التفاصيل'),
            '#منتحل',
            ['🔍 مراجعة البلاغ', ImpersonatorResource::getUrl('index')],
        ));
    }

    public static function contact(ContactMessage $message): void
    {
        $deletion = $message->subject === 'account_deletion';

        self::send(fn () => self::message(
            $deletion ? '⚠️' : '✉️', $deletion ? 'طلب حذف حساب' : 'رسالة جديدة من «تواصل معنا»',
            [
                '👤 الاسم' => self::bold($message->name),
                '📧 البريد' => '<code>'.self::e($message->email).'</code>',
                '🗂 الموضوع' => self::e(__('trust.contact_subjects.'.$message->subject, [], 'ar')),
                '🪪 الحساب' => $message->user ? 'عضو مسجّل (#'.$message->user->id.')' : 'زائر',
            ],
            self::quote($message->message, 1200, 'الرسالة')
                .($deletion ? "\n\n⏱ <i>يجب تنفيذ الطلب خلال 30 يوماً كحد أقصى.</i>" : ''),
            $deletion ? '#حذف_حساب #تواصل' : '#تواصل',
            ['💬 فتح الرسالة', ContactMessageResource::getUrl('index')],
        ));
    }

    /**
     * Builds the message now (the record is fresh) and sends it in the background.
     * Never lets an alert problem break the visitor's request.
     *
     * @param  callable(): array{text: string, button: array{0: string, 1: string}|null}  $build
     */
    protected static function send(callable $build): void
    {
        try {
            if (self::enabled()) {
                ['text' => $text, 'button' => $button] = $build();
                foreach (self::chatIds() as $chatId) {
                    SendAdminAlert::dispatch($chatId, $text, $button);
                }
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array<string, ?string>  $fields  label => HTML value; empty ones are left out
     * @param  array{0: string, 1: string}|null  $button
     * @return array{text: string, button: array{0: string, 1: string}|null}
     */
    public static function message(string $icon, string $title, array $fields, string $body, string $tags, ?array $button): array
    {
        $rows = collect($fields)
            ->reject(fn ($value) => blank(strip_tags((string) $value)))
            ->map(fn ($value, $label) => "<b>{$label}:</b> {$value}")
            ->implode("\n");

        $text = implode("\n", array_filter([
            "{$icon} <b>".self::e($title).'</b>',
            self::LINE,
            $rows,
            filled($body) ? "\n".$body : null,
            self::LINE,
            '🕒 '.now()->timezone(config('app.timezone'))->format('Y/m/d · H:i').'  |  '.self::e(Site::name('ar')),
            $tags,
        ]));

        return ['text' => $text, 'button' => $button];
    }

    protected static function quote(?string $text, int $limit, ?string $label = null): string
    {
        if (blank($text)) {
            return '';
        }

        return ($label ? "<b>📝 {$label}:</b>\n" : '').'<blockquote>'.self::e(Str::limit(trim($text), $limit)).'</blockquote>';
    }

    protected static function stars(?int $rating): string
    {
        $rating = max(0, min(5, (int) $rating));

        return str_repeat('★', $rating).str_repeat('☆', 5 - $rating)." ({$rating}/5)";
    }

    protected static function reporter(?string $name, ?string $voterKey): string
    {
        return match (true) {
            filled($name) => self::e($name),
            str_starts_with((string) $voterKey, 'o:') => 'صاحب القناة',
            default => 'زائر',
        };
    }

    protected static function bold(?string $text): ?string
    {
        return filled($text) ? '<b>'.self::e($text).'</b>' : null;
    }

    protected static function e(?string $text): string
    {
        return htmlspecialchars((string) $text, ENT_NOQUOTES, 'UTF-8');
    }
}
