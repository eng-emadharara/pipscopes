<?php

namespace App\Mail;

use App\Models\Review;
use App\Support\EarlyReviewAlerts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Tells the admins a review on an early-open channel looks suspicious (App\Support\EarlyReviewAlerts). */
class EarlyReviewAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param  list<string>  $reasons */
    public function __construct(public Review $review, public array $reasons)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '⚠️ إنذار تقييمات: '.$this->review->provider->name);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.early-review-alert',
            text: 'emails.early-review-alert-text',
            with: [
                'labels' => array_map(fn (string $reason) => EarlyReviewAlerts::REASONS[$reason] ?? $reason, $this->reasons),
                'adminUrl' => url('/admin/reviews').'?'.http_build_query(['tableFilters' => ['alerted' => ['isActive' => 'true']]]),
            ],
        );
    }
}
