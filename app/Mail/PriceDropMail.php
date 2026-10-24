<?php

namespace App\Mail;

use App\Models\PriceAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PriceDropMail extends Mailable
{
    use Queueable;

    public function __construct(public PriceAlert $alert) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Price drop: '.str($this->alert->product->name)->limit(60));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.price-drop');
    }
}
