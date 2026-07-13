<?php

namespace App\Mail;

use App\Mail\Concerns\AfdelingsCc;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Eén Mailable voor alle vijf de bibliotheekberichten. Onderwerp en tekst komen
 * uit het e-mailsjabloon dat de Beheerder beheert (met de variabelen al
 * ingevuld), zodat de inhoud aanpasbaar is zonder codewijziging.
 *
 * De CC naar de bibliotheekpostbus (config `sis.mail.cc.bibliotheek`) wordt
 * automatisch toegevoegd — de opdracht vraagt die bij elk bericht.
 */
class BibliotheekBericht extends Mailable
{
    use AfdelingsCc, Queueable, SerializesModels;

    public function __construct(
        public string $onderwerpRegel,
        public string $tekst,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->onderwerpRegel,
            cc: $this->afdelingsCc('bibliotheek'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.bibliotheek.bericht');
    }
}
