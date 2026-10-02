<?php

namespace App\Mail;

use App\Models\PlatformFeedback;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlatformFeedbackReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PlatformFeedback $feedback)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Afrik Appart platform feedback',
            replyTo: [new Address($this->feedback->email, $this->feedback->name)],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.platform-feedback');
    }
}