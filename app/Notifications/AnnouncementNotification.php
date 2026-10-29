<?php

namespace App\Notifications;

use App\Models\Announcement;
use App\Models\User;

/** An admin announcement (Admin → الإعلانات), in the member's language. */
class AnnouncementNotification extends MemberNotification
{
    public const TYPE = 'announcements';

    /** An admin's test send: not added to the announcement's stats or the weekly cap */
    public bool $isTest = false;

    /** @param list<string> $channels Picked when it was queued (App\Support\AnnouncementAudience) */
    public function __construct(public Announcement $announcement, public array $channels = [])
    {
    }

    public function via(User $user): array
    {
        return $this->channels;
    }

    public function subject(): string
    {
        return (string) $this->announcement->tr('title');
    }

    /** Paragraphs are separated by an empty line in the admin's text */
    public function lines(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R\s*\R/', (string) $this->announcement->tr('body')))));
    }

    public function action(): ?array
    {
        $url = $this->announcement->trackedUrl($this->channel);

        return $url ? [$this->announcement->tr('button_label') ?: __('اقرأ المزيد'), $url] : null;
    }

    public function image(): ?string
    {
        return $this->announcement->imageUrl();
    }
}
