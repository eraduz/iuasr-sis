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
