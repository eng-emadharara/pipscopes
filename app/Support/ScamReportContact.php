<?php

namespace App\Support;

use App\Mail\ScamReportMessageMail;
use App\Models\ScamReport;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * The admin panel reaching a member about their scam report: their Telegram (a chat link and
 * a message from our bot), their email, and the extra contact they wrote on the report.
 */
class ScamReportContact
{
    /** The member's Telegram @username, when they signed in with Telegram and have one */
    public static function telegramUsername(?User $user): ?string
    {
        $nickname = $user?->socialAccounts->firstWhere('provider', 'telegram')?->nickname;

        return $nickname ? ltrim($nickname, '@') : null;
    }

    /** The bot can write to members who signed in with Telegram and didn't block it */
    public static function canMessageOnTelegram(?User $user): bool
    {
        return $user && ! $user->telegram_blocked_at && NotificationPrefs::telegramChatId($user) && config('services.telegram.bot_token');
    }

    public static function email(?User $user): ?string
    {
        $email = trim((string) $user?->email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /** A link for the free "other contact" field: @name → Telegram, an email → mailto, a phone → WhatsApp */
    public static function contactUrl(?string $contact): ?string
    {
        $contact = trim((string) $contact);

        return match (true) {
            $contact === '' => null,
            (bool) filter_var($contact, FILTER_VALIDATE_EMAIL) => 'mailto:'.$contact,
            (bool) preg_match('#^(https?://)?(t\.me|telegram\.me)/([A-Za-z0-9_]{4,32})#i', $contact, $m) => 'https://t.me/'.$m[3],
            (bool) preg_match('#^@([A-Za-z][A-Za-z0-9_]{3,31})$#', $contact, $m) => 'https://t.me/'.$m[1],
            strlen($digits = preg_replace('/\D/', '', $contact)) >= 8 && (bool) preg_match('#^[+\d\s\-()]+$#', $contact) => 'https://wa.me/'.$digits,
            (bool) preg_match('#^https?://#i', $contact) => $contact,
            default => null,
        };
    }

    /** @return string|null the error, or null when it went out */
    public static function sendTelegram(ScamReport $report, string $text): ?string
    {
        $user = $report->user;
        if (! self::canMessageOnTelegram($user)) {
            return 'لا يمكن مراسلة هذا العضو عبر البوت.';
        }

        $target = $report->scamCase?->target_display;

        try {
            TelegramAppLogin::api('sendMessage', [
                'chat_id' => NotificationPrefs::telegramChatId($user),
                'text' => '🛟 <b>'.e('بخصوص بلاغك'.($target ? ' عن '.$target : '')).'</b>'."\n\n".e(Str::limit($text, 3500)),
                'parse_mode' => 'HTML',
                'link_preview_options' => ['is_disabled' => true],
                'reply_markup' => ['inline_keyboard' => [[['text' => 'بلاغاتي', 'url' => route('scam-reports.index')]]]],
            ]);
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'blocked') || str_contains($e->getMessage(), 'deactivated')) {
                $user->forceFill(['telegram_blocked_at' => now()])->saveQuietly();
            }

            return 'لم تصل الرسالة: '.$e->getMessage();
        }

        return null;
    }

    /** @return string|null the error, or null when it went out */
    public static function sendEmail(ScamReport $report, string $subject, string $body, ?string $replyTo): ?string
    {
        $email = self::email($report->user);
        if (! $email) {
            return 'لا يوجد بريد صالح لهذا العضو.';
        }

        try {
            Mail::to($email)->locale('ar')->send(new ScamReportMessageMail($report, $subject, $body, $replyTo));
        } catch (Throwable $e) {
            return 'لم يُرسل البريد: '.$e->getMessage();
        }

        return null;
    }
}
