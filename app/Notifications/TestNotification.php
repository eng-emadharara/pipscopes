<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\TelegramChannel;
use App\Support\NotificationPrefs;

/** Admin → إعدادات الإشعارات → "Send me a test": checks the mail settings and the bot, ignoring the switches. */
class TestNotification extends MemberNotification
{
    public const TYPE = 'review_status';

    public function via(User $user): array
    {
        return collect(NotificationPrefs::CHANNELS)
            ->filter(fn ($channel) => NotificationPrefs::canReach($user, $channel))
            ->map(fn ($channel) => $channel === 'telegram' ? TelegramChannel::class : 'mail')
            ->values()->all();
    }

    public function subject(): string
    {
        return __('رسالة تجريبية من :app', ['app' => config('app.name')]);
    }

    public function lines(): array
    {
        return [__('إذا وصلتك هذه الرسالة فالإشعارات تعمل على هذه القناة.')];
    }

    public function action(): ?array
    {
        return [__('فتح الموقع'), url('/')];
    }
}
