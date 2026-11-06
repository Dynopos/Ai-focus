<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Sends HTML that was rendered and logged before sending (so retries resend exactly the same email). */
class HtmlMessage extends Mailable
{
    use Queueable;

    public function __construct(public string $subjectLine, public string $htmlBody, public ?string $fromName = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), $this->fromName ?? config('vice_ceo.email.from_name')),
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->htmlBody);
    }
}
