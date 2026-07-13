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
