<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Paginatie in de huisstijl (compacte ‹ ›-knoppen). Zonder deze override
        // gebruikt Laravel de Tailwind-view, waarvan de SVG-pijlen zonder
        // Tailwind-CSS enorm groot worden en buiten het scherm vallen.
        Paginator::defaultView('vendor.pagination.iuasr');
        Paginator::defaultSimpleView('vendor.pagination.iuasr');

        // Verzoeklimiet op de noodtoegang (break-glass). Twee limieten tegelijk:
        // per gebruikersnaam+IP tegen het raden van één wachtwoord, en ruimer per
        // IP tegen het aflopen van meerdere gebruikersnamen vanaf één plek.
        // Bewust GEEN permanente blokkade van het account zelf: dan zou een
        // aanvaller de noodtoegang kunnen dichtzetten juist wanneer die nodig is.
        RateLimiter::for('noodlogin', fn (Request $request) => [
            Limit::perMinute((int) config('sis.noodaccount.max_pogingen'))
                ->by('noodlogin|'.mb_strtolower((string) $request->input('gebruikersnaam')).'|'.$request->ip()),
            Limit::perMinute((int) config('sis.noodaccount.max_pogingen_per_ip'))
                ->by('noodlogin-ip|'.$request->ip()),
        ]);
    }
}
