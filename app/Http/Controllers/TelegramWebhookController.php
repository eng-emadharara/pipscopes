<?php

namespace App\Http\Controllers;

use App\Support\TelegramAppLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/** What the bot receives from Telegram. Only Telegram knows the secret header, set when the bot is connected. */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(
            TelegramAppLogin::isOn() && hash_equals(TelegramAppLogin::webhookSecret(), (string) $request->header('X-Telegram-Bot-Api-Secret-Token')),
            403,
        );

        try {
            TelegramAppLogin::handle($request->all());
        } catch (Throwable $e) {
            // Anything but a 200 makes Telegram send the same update again and again
            Log::warning('Telegram update failed', ['error' => $e->getMessage()]);
        }

        return response()->noContent(200);
    }
}
