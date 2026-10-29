<?php

namespace App\Http\Controllers;

use App\Models\Announcement;

/** The button in an announcement: counts the click, then opens the admin's link with UTM tags. */
class AnnouncementClickController extends Controller
{
    public function __invoke(Announcement $announcement, string $channel)
    {
        abort_unless(in_array($channel, ['mail', 'telegram'], true) && $announcement->button_url, 404);

        $announcement->increment('clicks_count');

        return redirect()->away($announcement->destinationUrl($channel));
    }
}
