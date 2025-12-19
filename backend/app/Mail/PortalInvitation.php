<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Portal Invitation Email
 * 
 * Sent when a contact is invited to the client portal.
 */
class PortalInvitation extends BaseMailable
{
    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $recipientName,
        public string $recipientEmail,
        public string $confirmationUrl,
        public ?string $token = null,
        public ?string $companyName = null,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->getFromAddress(),
            subject: "You're Invited to " . ($this->companyName ?? $this->getAppName()),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.portal-invitation',
            with: [
                'recipientName' => $this->recipientName,
                'recipientEmail' => $this->recipientEmail,
                'confirmationUrl' => $this->confirmationUrl,
                'token' => $this->token,
                'companyName' => $this->companyName ?? $this->getAppName(),
                'siteUrl' => $this->getSiteUrl(),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
