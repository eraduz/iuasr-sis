<?php

use App\Http\Controllers\Auth\DevLoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InschrijvingController;
use App\Http\Controllers\StudentController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webroutes — IUASR SIS
|--------------------------------------------------------------------------
| Authenticatie: tijdelijk via dev-login (alleen lokaal), later Entra ID SSO.
| Rolscheiding wordt server-side afgedwongen via de 'rol'-middleware en Gates.
*/

// --- Inloggen (gast) ---
Route::get('/login', function () {
    $devUsers = app()->environment('local', 'testing')
        ? User::orderBy('rol')->get(['id', 'naam', 'rol'])
        : collect();

    return view('auth.login', compact('devUsers'));
})->name('login');

Route::post('/dev-login', [DevLoginController::class, 'store'])->name('dev-login');
Route::post('/logout', [DevLoginController::class, 'destroy'])->name('logout');

// --- Ingelogd ---
Route::middleware('auth')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Studenten inzien: Studentenzaken, Beheerder, Examencommissie, Directie.
    Route::middleware('rol:studentenzaken,beheerder,examencommissie,directie')->group(function () {
        Route::get('/studenten', [StudentController::class, 'index'])->name('studenten.index');
        Route::get('/studenten/{student}', [StudentController::class, 'show'])->name('studenten.show');
        Route::get('/studenten/{student}/bsn', [StudentController::class, 'bsn'])->name('studenten.bsn');
    });

    // Identiteit & inschrijving beheren: Studentenzaken, Beheerder.
    Route::middleware('rol:studentenzaken,beheerder')->group(function () {
        Route::get('/inschrijven', [InschrijvingController::class, 'create'])->name('inschrijven');
        Route::post('/inschrijven', [InschrijvingController::class, 'store'])->name('inschrijven.store');
        Route::view('/herinschrijven', 'placeholder')->name('herinschrijven');
        Route::view('/uitschrijven', 'placeholder')->name('uitschrijven');
        Route::view('/verklaringen', 'placeholder')->name('verklaringen');
        Route::view('/rapporten', 'placeholder')->name('rapporten');
    });

    // Docent — eigen vak.
    Route::middleware('rol:docent')->group(function () {
        Route::view('/mijn-vakken', 'placeholder')->name('mijn-vakken');
        Route::view('/cijferinvoer', 'placeholder')->name('cijferinvoer');
    });

    // Cijferinzage — Examencommissie & Directie.
    Route::middleware('rol:examencommissie,directie')->group(function () {
        Route::view('/cijferoverzicht', 'placeholder')->name('cijferoverzicht');
    });

    // Rapporten (inzage) voor examencommissie/directie hergebruiken de naam niet;
    // eigen route zodat de rol-scheiding zuiver blijft.
    Route::middleware('rol:examencommissie,directie')->group(function () {
        Route::view('/rapporten-inzage', 'placeholder')->name('rapporten.inzage');
    });

    // Beheer.
    Route::middleware('rol:beheerder')->group(function () {
        Route::view('/gebruikers', 'placeholder')->name('gebruikers');
        Route::view('/opzoektabellen', 'placeholder')->name('opzoektabellen');
        Route::view('/audit-log', 'placeholder')->name('audit-log');
    });
});
