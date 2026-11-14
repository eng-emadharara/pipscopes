<?php

namespace App\Mail;

use App\Models\ScamReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** A message from the team to a member about their scam report (App\Support\ScamReportContact). */
class ScamReportMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ScamReport $report, public string $subjectLine, public string $body, public ?string $replyAddress = null)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine, replyTo: $this->replyAddress ? [$this->replyAddress] : []);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.scam-report-message',
            text: 'emails.scam-report-message-text',
            with: ['target' => $this->report->scamCase?->target_display, 'url' => route('scam-reports.index')],
        );
    }
}
