<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** The signed-in member's own profile: photo, display name and social links. */
class ProfileController extends Controller
{
    /** Where an "@handle" typed for each platform points */
    protected const HANDLE_URLS = [
        'telegram_url' => 'https://t.me/',
        'twitter_url' => 'https://x.com/',
        'instagram_url' => 'https://instagram.com/',
        'youtube_url' => 'https://youtube.com/@',
        'tiktok_url' => 'https://tiktok.com/@',
        'facebook_url' => 'https://facebook.com/',
    ];

    public function edit(Request $request)
    {
        $user = $request->user()->load('socialAccounts');

        return view('profile.edit', [
            'user' => $user,
            'reviewsCount' => $user->reviews()->count(),
            'otherSessions' => self::otherSessions($request)->count(),
        ]);
    }

    /** The member's sessions on other browsers and devices (sessions are kept in the database) */
    protected static function otherSessions(Request $request)
    {
        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId());
    }

    /**
     * Signs the member out everywhere else: other devices lose their session, and a new remember token
     * makes their remember cookie useless. This device gets a cookie with the new token.
     */
    public function signOutOtherDevices(Request $request)
    {
        if (config('session.driver') === 'database') {
            self::otherSessions($request)->delete();
        }

        $request->user()->setRememberToken(Str::random(60));
        $request->user()->save();
        auth()->guard('web')->renewRecaller();

        return back()->with('success', __('تم تسجيل خروجك من كل الأجهزة الأخرى.'));
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $fields = array_keys(config('trust.socials'));

        // "@name" or "x.com/name" are accepted and turned into full links before validating
        $request->merge(['social_links' => collect($request->input('social_links', []))
            ->only($fields)
            ->map(fn ($value, $field) => $this->normalizeLink($field, (string) $value))
            ->all()]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=64,min_height=64'],
            'remove_avatar' => ['nullable', 'boolean'],
            'social_links' => ['nullable', 'array'],
            'social_links.*' => ['nullable', 'url:http,https', 'max:255'],
        ], [], [
            'name' => __('الاسم'),
            'avatar' => __('الصورة الشخصية'),
            ...collect($fields)->mapWithKeys(fn ($f) => ["social_links.$f" => __("trust.socials.$f")])->all(),
        ]);

        $user->name = trim($data['name']);
        $user->social_links = array_filter($data['social_links'] ?? []) ?: null;

        if ($request->file('avatar') || $request->boolean('remove_avatar')) {
            $old = $user->hasUploadedAvatar() ? $user->avatar_url : null;
            $user->avatar_url = $request->file('avatar')?->store('avatars', 'public');
            if ($old) {
                Storage::disk('public')->delete($old);
            }
        }

        if (! $user->isDirty()) {
            return redirect()->route('profile.edit')->with('success', __('لا توجد تغييرات للحفظ.'));
        }

        $user->save();

        return redirect()->route('profile.edit')->with('success', __('تم حفظ ملفك الشخصي.'));
    }

    protected function normalizeLink(string $field, string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }
        if (Str::startsWith($value, '@') && isset(self::HANDLE_URLS[$field])) {
            return self::HANDLE_URLS[$field].ltrim($value, '@');
        }
        if (! preg_match('~^[a-z][a-z0-9+.-]*://~i', $value)) {
            return 'https://'.$value;
        }

        return $value;
    }
}
