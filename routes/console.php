<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Toon een inspirerende quote');

// Onderwijsnieuws voor het bestuursdashboard: dagelijks om 23:00 op de achtergrond
// ophalen (whitelist-hosts). Vereist een draaiende scheduler (`php artisan schedule:work`
// of een cron die `schedule:run` elke minuut aanroept).
Schedule::command('nieuws:ophalen')->dailyAt('23:00')->withoutOverlapping();

// HR: verjaardagsfelicitaties en meldingen van startend wettelijk verlof.
// 's Ochtends, zodat Personeelszaken meteen bij aanvang van de dag op de hoogte is.
Schedule::command('hr:notificaties')->dailyAt('07:00')->withoutOverlapping();

// Bibliotheek: herinnering vóór de vervaldatum, waarschuwing bij te laat (student)
// en de herhaalherinnering voor docenten. Idempotent (kijkt in het e-maillogboek),
// dus een dubbele run verstuurt niets dubbel.
Schedule::command('bibliotheek:herinneringen')->dailyAt('07:30')->withoutOverlapping();

// Systeemmeldingen: alleen HISTORIE opruimen. Het VERDWIJNEN van een melding
// hangt hier niet van af — dat volgt uit het venster van/tot en gebeurt vanzelf
// op de seconde. Valt de scheduler uit, dan blijft er hooguit oude historie
// staan; er blijft nooit een melding op de schermen hangen.
Schedule::command('sis:meldingen-opruimen')->weeklyOn(1, '04:00')->withoutOverlapping();
