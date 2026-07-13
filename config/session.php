<?php

/*
|--------------------------------------------------------------------------
| Sessie-instellingen
|--------------------------------------------------------------------------
|
| Alleen de instellingen die afwijken van de standaard van het framework; de
| rest (cookienaam, pad, opslagtabel) komt daaruit. Deze waarden horen op elke
| omgeving gelijk te zijn en staan daarom in Git in plaats van in .env.
|
*/

return [

    // Sessies in de database, zodat ze meelopen in de back-up en niet op een
    // gedeelde schijf belanden.
    'driver' => env('SESSION_DRIVER', 'database'),

    'lifetime' => (int) env('SESSION_LIFETIME', 120),

    // Sessies bevatten identiteit en rol. Versleuteld opslaan; de standaard van
    // het framework is false, dus dit MOET expliciet worden vastgelegd.
    'encrypt' => env('SESSION_ENCRYPT', true),

    // Cookie alleen over HTTPS versturen. Dit is de enige sessie-instelling die
    // PER OMGEVING verschilt: op de RDP en Plesk (https) true, bij lokale
    // ontwikkeling over http false — staat hij daar op true, dan stuurt de
    // browser de cookie niet terug en volgt er een 419 Page Expired.
    'secure' => env('SESSION_SECURE_COOKIE', false),

    // Cookie niet benaderbaar vanuit JavaScript.
    'http_only' => env('SESSION_HTTP_ONLY', true),

];
