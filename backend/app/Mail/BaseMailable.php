<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Base Mailable Class
 * 
 * All email mailables should extend this class to ensure consistent
 * styling and behavior across all emails.
 * 
 * Features:
 * - Consistent from address configuration
 * - Shared email layout
 * - Common helper methods
 */
abstract class BaseMailable extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Get the default "from" address.
     */
    protected function getFromAddress(): Address
    {
        return new Address(
            config('mail.from.address'),
            config('mail.from.name')
        );
    }

    /**
     * Get the site URL for use in emails.
     */
    protected function getSiteUrl(): string
    {
        return config('app.url');
    }

    /**
     * Get the client portal URL.
     */
    protected function getClientUrl(): string
    {
        return config('app.client_url', config('app.url'));
    }

    /**
     * Get the app name.
     */
    protected function getAppName(): string
    {
        return config('app.name');
    }
}

