<?php

namespace App\Mail\Concerns;

use Illuminate\Mail\Mailables\Address;

/**
 * Voegt automatisch een CC naar de afdelingspostbus toe (config `sis.mail.cc`),
 * zodat medewerkers zien welke SIS-e-mails zijn verstuurd. De CC wordt nooit
 * dubbel gezet wanneer de afdelingspostbus zelf al de primaire ontvanger is.
 *
 * Gebruik in een Mailable: geef in de Envelope `cc: $this->afdelingsCc('hr')` mee.
 */
trait AfdelingsCc
{
    /**
     * @return array<int, Address>
     */
    protected function afdelingsCc(string $module): array
    {
        $adres = config('sis.mail.cc.'.$module);

        return $adres ? [new Address($adres)] : [];
    }
}
