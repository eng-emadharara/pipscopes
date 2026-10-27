<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Http;

/** Fake t.me pages, shaped like the real ones (see App\Services\TelegramChannel). */
trait FakesTelegram
{
    /**
     * @param  array<string, array{title?: string, extra?: string, verified?: bool}>  $channels  username => page details;
     *                                                                                       anything not listed is "not found"
     */
    protected function fakeTelegram(array $channels = []): void
    {
        $fakes = ['cdn*.telesco.pe/*' => Http::response(base64_decode(
            // 1x1 JPEG
            '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA='
        ), 200, ['Content-Type' => 'image/jpeg'])];

        foreach ($channels as $username => $page) {
            $title = $page['title'] ?? $username;
            $extra = $page['extra'] ?? '12 345 subscribers';
            $verified = ($page['verified'] ?? false) ? '<i class="verified-icon"></i>' : '';

            $fakes["t.me/s/$username"] = Http::response(
                '<span class="counter_value">16</span> <span class="counter_type">photos</span>'
                .'<div class="tgme_widget_message"><span class="tgme_widget_message_views">1.2K</span><time datetime="2026-10-20T10:00:00+00:00"></time></div>'
                .'<div class="tgme_widget_message"><span class="tgme_widget_message_views">800</span><time datetime="2026-10-25T10:00:00+00:00"></time></div>'
            );
            $fakes["t.me/$username"] = Http::response(
                '<meta property="og:title" content="'.e($title).'">'
                .'<meta property="og:image" content="https://cdn4.telesco.pe/file/'.$username.'.jpg">'
                .'<meta property="og:description" content="Daily gold signals">'
                .'<div class="tgme_page_title"><span>'.e($title).'</span>'.$verified.'</div>'
                .'<div class="tgme_page_extra">'.$extra.'</div>'
            );
        }

        // Unknown usernames: Telegram's generic contact page
        $fakes['t.me/*'] = Http::response('<meta property="og:title" content="Telegram: Contact @someone">');

        Http::fake($fakes);
    }
}
