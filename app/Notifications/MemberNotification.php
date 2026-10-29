<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\NotificationPrefs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * A notification to a member about their own activity, by email and/or Telegram
 * (App\Support\NotificationPrefs decides which). Subclasses give the text once; this class
 * turns it into the branded email and the Telegram message, each with a way to switch it off.
 */
abstract class MemberNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Key in NotificationPrefs::TYPES */
    public const TYPE = '';

    public int $tries = 3;

    public array $backoff = [60, 600];

    /** The review may be deleted before the queue gets to it */
    public bool $deleteWhenMissingModels = true;

    abstract public function subject(): string;

    /** @return list<string> Paragraphs of plain text */
    abstract public function lines(): array;

    /** @return array{0: string, 1: string}|null [button label, url] */
    abstract public function action(): ?array;

    /** Text quoted under the paragraphs, such as the owner's reply */
    public function quote(): ?string
    {
        return null;
    }

    /** Image shown above the text (absolute URL) */
    public function image(): ?string
    {
        return null;
    }

    /** The channel being written for, while toMail() / toTelegram() run */
    protected string $channel = 'mail';

    public function via(User $user): array
    {
        return NotificationPrefs::channels($user, static::TYPE);
    }

    public function toMail(User $user): MailMessage
    {
        $this->channel = 'mail';
        $unsubscribe = self::unsubscribeUrl($user, static::TYPE, 'mail');

        return (new MailMessage)
            ->subject($this->subject())
            ->view(['emails.notification', 'emails.notification-text'], [
                'user' => $user,
                'title' => $this->subject(),
                'lines' => $this->lines(),
                'quote' => $this->quote(),
                'action' => $this->action(),
                'image' => $this->image(),
                'unsubscribe' => $unsubscribe,
                'typeLabel' => __(NotificationPrefs::TYPES[static::TYPE][0]),
            ])
            // Lets mail apps offer their own "unsubscribe" button
            ->withSymfonyMessage(fn ($message) => $message->getHeaders()
                ->addTextHeader('List-Unsubscribe', "<$unsubscribe>")
                ->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click'));
    }

    /** @return array{text: string, buttons: list<array{text: string, url: string}>, photo: ?string} */
    public function toTelegram(User $user): array
    {
        $this->channel = 'telegram';
        $text = '<b>'.e($this->subject()).'</b>';
        foreach ($this->lines() as $line) {
            $text .= "\n\n".e($line);
        }
        if ($quote = $this->quote()) {
            $text .= "\n\n<blockquote>".e(\Illuminate\Support\Str::limit($quote, 600)).'</blockquote>';
        }

        $buttons = [];
        if ($action = $this->action()) {
            $buttons[] = ['text' => $action[0], 'url' => $action[1]];
        }
        $buttons[] = ['text' => __('إيقاف هذه الإشعارات'), 'url' => self::unsubscribeUrl($user, static::TYPE, 'telegram')];

        return ['text' => $text, 'buttons' => $buttons, 'photo' => $this->image()];
    }

    /** One-click "stop these" link, valid without signing in */
    public static function unsubscribeUrl(User $user, string $type, string $channel): string
    {
        return URL::signedRoute('notifications.unsubscribe', ['user' => $user->id, 'type' => $type, 'channel' => $channel]);
    }
}
