<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ActivityNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $details)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->details['title']);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.activity-notification', with: $this->details);
    }
}