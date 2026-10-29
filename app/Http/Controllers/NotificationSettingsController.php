<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\NotificationPrefs;
use Illuminate\Http\Request;

/** A member's notification choices (Profile → الإشعارات), and the one-click "stop these" links in each message. */
class NotificationSettingsController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user()->load('socialAccounts');

        return view('notifications.edit', [
            'user' => $user,
            'types' => collect(NotificationPrefs::TYPES)->filter(fn ($meta, $type) => NotificationPrefs::siteEnabled("types.$type")),
            'channels' => collect(NotificationPrefs::CHANNELS)->filter(fn ($channel) => NotificationPrefs::siteEnabled("channels.$channel"))->values(),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $input = (array) $request->input('prefs', []);

        $prefs = (array) ($user->notification_prefs ?? []);
        foreach (array_keys(NotificationPrefs::TYPES) as $type) {
            foreach (NotificationPrefs::CHANNELS as $channel) {
                // Only channels shown on the form; the others keep their saved choice
                if (isset($input[$type][$channel])) {
                    $prefs[$type][$channel] = (bool) $input[$type][$channel];
                }
            }
        }

        $user->forceFill(['notification_prefs' => $prefs])->save();

        return redirect()->route('notifications.edit')->with('success', __('تم حفظ إعدادات الإشعارات.'));
    }

    /** Opened from an email or Telegram message: asks before switching off, so link scanners don't do it */
    public function unsubscribe(Request $request, User $user, string $type, string $channel)
    {
        $this->ensureKnown($type, $channel);

        return view('notifications.unsubscribe', [
            'typeLabel' => __(NotificationPrefs::TYPES[$type][0]),
            'channelLabel' => $this->channelLabel($channel),
            'done' => ! NotificationPrefs::wants($user, $type, $channel),
        ]);
    }

    /** The page's button, and mail apps' own one-click "unsubscribe" (List-Unsubscribe-Post) */
    public function confirmUnsubscribe(Request $request, User $user, string $type, string $channel)
    {
        $this->ensureKnown($type, $channel);

        $prefs = (array) ($user->notification_prefs ?? []);
        $prefs[$type][$channel] = false;
        $user->forceFill(['notification_prefs' => $prefs])->save();

        if (! $request->expectsJson() && $request->has('_token')) {
            return back()->with('success', __('تم إيقاف هذه الإشعارات.'));
        }

        return response()->noContent();
    }

    protected function ensureKnown(string $type, string $channel): void
    {
        abort_unless(isset(NotificationPrefs::TYPES[$type]) && in_array($channel, NotificationPrefs::CHANNELS, true), 404);
    }

    protected function channelLabel(string $channel): string
    {
        return $channel === 'telegram' ? __('تيليجرام') : __('البريد الإلكتروني');
    }
}
