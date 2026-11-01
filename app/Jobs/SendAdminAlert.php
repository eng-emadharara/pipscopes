<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/** One team alert (App\Support\AdminAlerts) to one chat; a job per chat, so a retry doesn't repeat it elsewhere. */
class SendAdminAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [60, 600];

    /** @param  array{0: string, 1: string}|null  $button  [label, url] */
    public function __construct(public string $chatId, public string $text, public ?array $button = null) {}

    public function handle(): void
    {
        if ($error = static::sendTo($this->chatId, $this->text, $this->button)) {
            Log::warning('Admin Telegram alert not delivered', ['chat_id' => $this->chatId, 'error' => $error]);
        }
    }

    /**
     * @param  array{0: string, 1: string}|null  $button
     * @return string|null Why it didn't go out, or null when sent
     */
    public static function sendTo(string $chatId, string $text, ?array $button = null): ?string
    {
        $token = config('services.telegram.bot_token');
        if (! $token) {
            return 'لم يُضبط توكن البوت';
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'link_preview_options' => ['is_disabled' => true],
        ];
        $post = fn (array $payload) => Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", $payload);

        $response = $button
            ? $post($payload + ['reply_markup' => ['inline_keyboard' => [[['text' => $button[0], 'url' => $button[1]]]]]])
            : $post($payload);

        // Telegram refuses button links to local addresses (e.g. *.test); the link goes in the text instead
        if ($button && $response->status() === 400) {
            $response = $post(['text' => $text."\n\n🔗 ".$button[1]] + $payload);
        }

        if ($response->successful()) {
            return null;
        }

        // Rate limits and Telegram outages: let the queue try again later
        if ($response->status() === 429 || $response->serverError()) {
            throw new RuntimeException('Telegram '.$response->status().': '.$response->json('description'));
        }

        return $response->status().' '.$response->json('description');
    }
}
