<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SellerNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $emailSubject,
        public readonly string $emailMessage,
        public readonly ?string $sellerName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.seller_notification',
            with: [
                'emailSubject' => $this->emailSubject,
                'emailMessage' => $this->emailMessage,
                'sellerName'   => $this->sellerName,
            ],
        );
    }
}
