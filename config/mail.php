<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Standaard mailer
    |--------------------------------------------------------------------------
    | Voor Microsoft 365: zet MAIL_MAILER=graph en vul de MS_GRAPH_*-variabelen.
    | Standaard 'log' (mails alleen naar het logbestand) zodat er niets per
    | ongeluk de deur uit gaat vóór de mailkoppeling is ingericht.
    */

    'default' => env('MAIL_MAILER', 'log'),

    'mailers' => [

        // Microsoft 365 via de Graph API (OAuth2 client credentials, Mail.Send).
        // De app-registratie levert tenant/client/secret; MS_GRAPH_FROM is de
        // verzendmailbox (mag een GEDEELDE mailbox zijn, bv. examencommissie@iuasr.nl),
        // afgeschermd met een Application Access Policy in Entra.
        'graph' => [
            'transport' => 'microsoft-graph',
            'tenant' => env('MS_GRAPH_TENANT_ID'),
            'client_id' => env('MS_GRAPH_CLIENT_ID'),
            'client_secret' => env('MS_GRAPH_CLIENT_SECRET'),
            'from' => env('MS_GRAPH_FROM', env('MAIL_FROM_ADDRESS')),
        ],

        // Klassieke SMTP (bv. als terugval). Let op: Microsoft 365 heeft SMTP AUTH
        // (basic auth) voor de meeste tenants uitgeschakeld.
        'smtp' => [
            'transport' => 'smtp',
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', 'smtp.office365.com'),
            'port' => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => ['graph', 'log'],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Globale "van"-afzender
    |--------------------------------------------------------------------------
    | Bij de Graph-mailer bepaalt MS_GRAPH_FROM de verzendmailbox; dit adres is
    | het zichtbare afzenderadres wanneer een mail zelf geen From meegeeft.
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@iuasr.nl'),
        'name' => env('MAIL_FROM_NAME', 'IUASR'),
    ],

];
