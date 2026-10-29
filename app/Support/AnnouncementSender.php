<?php

namespace App\Support;

use App\Models\Announcement;
use App\Notifications\AnnouncementNotification;

/**
 * Queues an announcement for each member in its audience. The messages themselves go out in the
 * background (routes/console.php), and their results fill sent_count / failed_count as they do.
 */
class AnnouncementSender
{
    /** @return int Members it was queued for; 0 if it was already being sent */
    public static function send(Announcement $announcement): int
    {
        // Claim it, so the scheduler and an admin click can't send it twice
        $claimed = Announcement::whereKey($announcement->id)->whereIn('status', ['draft', 'scheduled'])
            ->update(['status' => 'sending', 'updated_at' => now()]);
        if (! $claimed) {
            return 0;
        }

        $announcement->refresh();
        $count = 0;
        foreach (AnnouncementAudience::recipients((array) $announcement->audience) as [$user, $channels]) {
            $user->notify(new AnnouncementNotification($announcement, $channels));
            $count++;
        }

        $announcement->update(['status' => 'sent', 'sent_at' => now(), 'recipients_count' => $count]);

        return $count;
    }

    /** Scheduled announcements whose time has come (run every minute) */
    public static function sendDue(): int
    {
        return Announcement::where('status', 'scheduled')->where('scheduled_at', '<=', now())->get()
            ->sum(fn (Announcement $announcement) => self::send($announcement) > 0 ? 1 : 0);
    }
}
