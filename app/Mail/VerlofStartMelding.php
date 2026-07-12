<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Melding aan Personeelszaken dat een wettelijk verlof (zwangerschaps-/bevallings-,
 * geboorte- of ouderschapsverlof) vandaag ingaat, zodat HR de UWV-uitkering en de
 * vervanging tijdig regelt.
 */
class VerlofStartMelding extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $medewerkerNaam,
        public string $verloftypeLabel,
        public string $van,
        public string $tot,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Wettelijk verlof gestart — '.$this->medewerkerNaam.' ('.$this->verloftypeLabel.')');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.hr.verlof-start');
    }
}
