<?php

namespace Database\Seeders;

use App\Models\Nieuwsbericht;
use App\Models\Nieuwsbron;
use Illuminate\Database\Seeder;

/**
 * De onderwijsnieuwsbronnen voor het bestuursdashboard (opdrachtgever 2026-07-12).
 * Idempotent (firstOrCreate op url). De berichten zelf worden opgehaald door het
 * commando `nieuws:ophalen` (scheduler, dagelijks 23:00) — niet hier (geen internet
 * in een seed).
 */
class NieuwsbronSeeder extends Seeder
{
    public function run(): void
    {
        // Vereniging Hogescholen — nette Atom-feed (automatisch).
        Nieuwsbron::firstOrCreate(
            ['url' => 'https://www.vereniginghogescholen.nl/actueel/actualiteiten.atom'],
            ['naam' => 'Vereniging Hogescholen', 'type' => 'atom', 'categorie' => 'Hoger onderwijs', 'volgorde' => 1, 'actief' => true]
        );

        // Onderwijsinspectie — een JavaScript-app zonder feed/API; automatisch ophalen
        // is niet betrouwbaar. Daarom HANDMATIG: Beheer voegt de belangrijke items toe.
        $oi = Nieuwsbron::firstOrCreate(
            ['url' => 'https://www.onderwijsinspectie.nl/actueel/nieuws'],
            ['naam' => 'Onderwijsinspectie', 'type' => 'handmatig', 'categorie' => 'Toezicht', 'volgorde' => 2, 'actief' => true]
        );

        // Startpunt-item (curatielink), zodat de bron niet leeg is; Beheer vervangt/vult aan.
        $link = 'https://www.onderwijsinspectie.nl/actueel/nieuws';
        Nieuwsbericht::firstOrCreate(
            ['link_hash' => Nieuwsbericht::hashVoor($link)],
            [
                'nieuwsbron_id' => $oi->id,
                'titel' => 'Actueel nieuws van de Onderwijsinspectie',
                'samenvatting' => 'Bekijk de laatste nieuwsberichten van de Inspectie van het Onderwijs.',
                'link' => $link,
                'opgehaald_op' => now(),
            ]
        );
    }
}
