<?php

namespace App\Jobs;

use App\Models\ChannelWatch;
use App\Models\Provider;
use App\Support\TelegramAppLogin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/** One alert to every chat following a channel (App\Support\ChannelWatch::alert), sent from the queue */
class SendWatchAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $providerId, public string $text) {}

    public function handle(): void
    {
        $provider = Provider::find($this->providerId);
        if (! $provider || ! TelegramAppLogin::isOn()) {
            return;
        }

        app()->setLocale('ar');

        ChannelWatch::where('provider_id', $provider->id)->each(function (ChannelWatch $watch) use ($provider) {
            try {
                TelegramAppLogin::api('sendMessage', [
                    'chat_id' => $watch->chat_id,
                    'text' => $this->text,
                    'reply_markup' => ['inline_keyboard' => [
                        [['text' => '📄 '.__('صفحة القناة'), 'url' => route('providers.show', $provider)]],
                        [['text' => '🔕 '.__('إيقاف تنبيهات هذه القناة'), 'callback_data' => 'w:off:'.$provider->id]],
                    ]],
                ]);
            } catch (RuntimeException $e) {
                // The member blocked the bot or deleted the chat: stop writing to it
                if (str_contains($e->getMessage(), 'blocked') || str_contains($e->getMessage(), 'chat not found') || str_contains($e->getMessage(), 'deactivated')) {
                    $watch->delete();
                }
            }
        });
    }
}
