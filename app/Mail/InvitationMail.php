<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Invitation $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->invitation->email],
            subject: __('You have been invited'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invitation',
            with: [
                'acceptUrl' => route('invitations.accept', $this->invitation->token),
                'expiresAt' => $this->invitation->expires_at,
            ],
        );
    }
}
