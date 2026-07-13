<?php

/*
|--------------------------------------------------------------------------
| Applicatie-instellingen
|--------------------------------------------------------------------------
|
| Laravel valt terug op zijn eigen standaarden voor elk configbestand dat
| hier niet staat. Die standaarden zijn Engelstalig en UTC — niet wat dit
| systeem nodig heeft. De waarden hieronder leggen de gedeelde instellingen
| vast in Git, zodat ELKE omgeving (ontwikkeling, test op de RDP, Plesk) ze
| automatisch met een `git pull` krijgt en niemand ze per machine in .env
| hoeft over te typen. De env-sleutels blijven bestaan zodat een omgeving in
| uitzonderingsgevallen kan afwijken; de terugval is de juiste waarde.
|
| Sleutels die hier niet staan (providers, cipher, maintenance) komen uit de
| standaardconfiguratie van het framework.
|
*/

return [

    'name' => env('APP_NAME', 'IUASR Management Systeem'),

    // Alleen 'production' schakelt de veiligheidsstand in; per omgeving in .env.
    'env' => env('APP_ENV', 'production'),

    // Nooit true op productie: een stacktrace lekt persoonsgegevens.
    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost'),

    // Nederlandse tijd. Zonder deze regel rekent Laravel in UTC en wijken alle
    // getoonde tijdstippen (ziekmeldingen, verlof, presentie, audit-log) af van
    // de klok op kantoor.
    'timezone' => env('APP_TIMEZONE', 'Europe/Amsterdam'),

    // Taal in UI en documentatie is Nederlands (U-vorm).
    'locale' => env('APP_LOCALE', 'nl'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'nl'),

    // Synthetische data wordt in het Nederlands gegenereerd.
    'faker_locale' => env('APP_FAKER_LOCALE', 'nl_NL'),

    // Versleutelt gevoelige velden (BSN, rekeningnummer) en de sessies. Moet
    // gelijk zijn op elke omgeving die databasesnapshots uitwisselt; zie .env.example.
    'key' => env('APP_KEY'),

];
