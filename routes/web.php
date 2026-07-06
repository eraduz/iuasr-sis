<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webroutes — IUASR SIS
|--------------------------------------------------------------------------
|
| Fase 2-aanzet. Authenticatie verloopt straks via Entra ID (SSO/OIDC); de
| onderstaande routes zijn de kern-navigatie die overeenkomt met de leidende
| designs in IUASR/iuasr-sis. Rolscheiding wordt server-side afgedwongen via
| Gates (AutorisatieServiceProvider) en de 'rol'-middleware.
|
| Let op: de controllers/acties per pagina worden in Fase 3 (kern-CRUD) en
| Fase 4 (cijfers) ingevuld. Nu is de routestructuur en het rolregime gezet.
*/

Route::view('/login', 'auth.login')->name('login');

// Kern-navigatie (in Fase 3 achter Entra-auth-middleware te plaatsen).
Route::get('/', DashboardController::class.'@index')->name('dashboard');

// Studentenzaken — identiteit & inschrijving (geen cijfers).
Route::middleware('rol:studentenzaken,beheerder')->group(function () {
    Route::view('/studenten', 'placeholder')->name('studenten.index');
    Route::view('/inschrijven', 'placeholder')->name('inschrijven');
    Route::view('/herinschrijven', 'placeholder')->name('herinschrijven');
    Route::view('/uitschrijven', 'placeholder')->name('uitschrijven');
    Route::view('/verklaringen', 'placeholder')->name('verklaringen');
});

// Docent — eigen vak.
Route::middleware('rol:docent')->group(function () {
    Route::view('/mijn-vakken', 'placeholder')->name('mijn-vakken');
    Route::view('/cijferinvoer', 'placeholder')->name('cijferinvoer');
});

// Cijferinzage — examencommissie & directie.
Route::middleware('rol:examencommissie,directie')->group(function () {
    Route::view('/cijferoverzicht', 'placeholder')->name('cijferoverzicht');
});

// Beheer.
Route::middleware('rol:beheerder')->group(function () {
    Route::view('/gebruikers', 'placeholder')->name('gebruikers');
    Route::view('/opzoektabellen', 'placeholder')->name('opzoektabellen');
    Route::view('/audit-log', 'placeholder')->name('audit-log');
});
