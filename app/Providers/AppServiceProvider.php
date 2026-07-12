<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
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
    }
}
