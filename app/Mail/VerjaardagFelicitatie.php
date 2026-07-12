<?php

namespace App\Mail;

use App\Mail\Concerns\AfdelingsCc;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Automatische verjaardagsfelicitatie aan een medewerker (CC Personeelszaken). */
class VerjaardagFelicitatie extends Mailable
{
    use AfdelingsCc, Queueable, SerializesModels;

    public function __construct(public string $voornaam) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Gefeliciteerd met uw verjaardag!',
            cc: $this->afdelingsCc('hr'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.hr.verjaardag');
    }
}
