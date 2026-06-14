<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CampaignEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public array $payload,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->payload['subject'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.campaign',
            with: [
                'payload' => $this->payload,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
