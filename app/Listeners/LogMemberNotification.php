<?php

namespace App\Listeners;

use App\Models\NotificationLog;
use App\Notifications\AnnouncementNotification;
use App\Notifications\Channels\TelegramChannel;
use App\Notifications\MemberNotification;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;

/** Writes every member notification to Admin → سجل الإشعارات. */
class LogMemberNotification
{
    public function handle(NotificationSent|NotificationFailed $event): void
    {
        if (! $event->notification instanceof MemberNotification) {
            return;
        }

        $failed = $event instanceof NotificationFailed;
        // TelegramChannel returns false for a refused message and reports it as failed itself
        if (! $failed && $event->response === false) {
            return;
        }

        $announcement = $event->notification instanceof AnnouncementNotification && ! $event->notification->isTest
            ? $event->notification->announcement : null;
        $announcement?->increment($failed ? 'failed_count' : 'sent_count');

        NotificationLog::create([
            'user_id' => $event->notifiable->getKey(),
            'announcement_id' => $announcement?->id,
            'type' => $event->notification::TYPE,
            'channel' => $event->channel === TelegramChannel::class ? 'telegram' : $event->channel,
            'status' => $failed ? 'failed' : 'sent',
            'subject' => \Illuminate\Support\Str::limit($event->notification->subject(), 250),
            'error' => $failed ? \Illuminate\Support\Str::limit((string) ($event->data['error'] ?? ''), 490) : null,
        ]);
    }
}
