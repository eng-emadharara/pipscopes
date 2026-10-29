<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\MemberNotification;
use App\Support\NotificationPrefs;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends a member notification as a private message from our bot. Members who signed in with
 * Telegram gave the bot permission to write to them (request_access: write).
 */
class TelegramChannel
{
    /** Telegram allows 1024 characters under a photo; kept a little lower for the HTML tags */
    protected const CAPTION_LIMIT = 1000;

    /** False when the message didn't go out, so it isn't logged as sent (App\Listeners\LogMemberNotification) */
    public function send(User $user, MemberNotification $notification): bool
    {
        $chatId = NotificationPrefs::telegramChatId($user);
        $token = config('services.telegram.bot_token');
        if (! $chatId || ! $token) {
            return false;
        }

        $message = $notification->toTelegram($user);
        $api = "https://api.telegram.org/bot{$token}";
        $markup = $message['buttons'] ? ['inline_keyboard' => array_map(fn ($b) => [$b], $message['buttons'])] : null;
        $photo = $message['photo'] ?? null;

        if ($photo && mb_strlen(strip_tags($message['text'])) <= self::CAPTION_LIMIT) {
            // Short enough: one message, the picture with the text under it
            $response = Http::timeout(15)->post("$api/sendPhoto", array_filter([
                'chat_id' => $chatId, 'photo' => $photo, 'caption' => $message['text'], 'parse_mode' => 'HTML', 'reply_markup' => $markup,
            ]));
        } else {
            // Longer text can't be a caption: the picture first, then the message
            if ($photo) {
                Http::timeout(15)->post("$api/sendPhoto", ['chat_id' => $chatId, 'photo' => $photo]);
            }
            $response = Http::timeout(10)->post("$api/sendMessage", array_filter([
                'chat_id' => $chatId,
                'text' => $message['text'],
                'parse_mode' => 'HTML',
                'link_preview_options' => ['is_disabled' => true],
                'reply_markup' => $markup,
            ]));
        }

        if ($response->successful()) {
            return true;
        }

        // Rate limits and Telegram outages: let the queue try again later
        if ($response->status() === 429 || $response->serverError()) {
            throw new RuntimeException('Telegram '.$response->status().': '.$response->json('description'));
        }

        // Blocked the bot or deleted their account: stop writing to them until they sign in again
        if ($response->status() === 403) {
            $user->forceFill(['telegram_blocked_at' => now()])->saveQuietly();
        }

        event(new NotificationFailed($user, $notification, 'telegram', ['error' => $response->status().' '.$response->json('description')]));

        return false;
    }
}
