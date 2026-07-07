<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\DevLoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GebruikerController;
use App\Http\Controllers\InschrijvingActiesController;
use App\Http\Controllers\InschrijvingController;
use App\Http\Controllers\RapportController;
use App\Http\Controllers\ReferentieController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\VerklaringController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webroutes — IUASR SIS
|--------------------------------------------------------------------------
| Authenticatie: tijdelijk via dev-login (alleen lokaal), later Entra ID SSO.
| Rolscheiding wordt server-side afgedwongen via de 'rol'-middleware en Gates.
*/

Route::get('/login', function () {
    $devUsers = app()->environment('local', 'testing')
        ? User::orderBy('rol')->get(['id', 'naam', 'rol'])
        : collect();

    return view('auth.login', compact('devUsers'));
})->name('login');

Route::post('/dev-login', [DevLoginController::class, 'store'])->name('dev-login');
Route::post('/logout', [DevLoginController::class, 'destroy'])->name('logout');

Route::middleware('auth')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // --- Studenten inzien: SZ, Beheerder, Examencommissie, Directie ---
    Route::middleware('rol:studentenzaken,beheerder,examencommissie,directie')->group(function () {
        Route::get('/studenten', [StudentController::class, 'index'])->name('studenten.index');
        Route::get('/studenten/{student}', [StudentController::class, 'show'])->name('studenten.show');
        Route::get('/studenten/{student}/bsn', [StudentController::class, 'bsn'])->name('studenten.bsn');
    });

    // --- Identiteit & inschrijving beheren: SZ, Beheerder ---
    Route::middleware('rol:studentenzaken,beheerder')->group(function () {
        // Inschrijven
        Route::get('/inschrijven', [InschrijvingController::class, 'create'])->name('inschrijven');
        Route::post('/inschrijven', [InschrijvingController::class, 'store'])->name('inschrijven.store');

        // Muteren
        Route::get('/studenten/{student}/muteren', [StudentController::class, 'edit'])->name('studenten.edit');
        Route::put('/studenten/{student}', [StudentController::class, 'update'])->name('studenten.update');

        // Schorsen (één klik, omkeerbaar)
        Route::post('/studenten/{student}/schorsen', [InschrijvingActiesController::class, 'schors'])->name('studenten.schors');

        // Interne notities per student
        Route::post('/studenten/{student}/notities', [StudentController::class, 'notitieStore'])->name('studenten.notities.store');
        Route::delete('/studenten/{student}/notities/{notitie}', [StudentController::class, 'notitieDestroy'])->name('studenten.notities.destroy');

        // Herinschrijven
        Route::get('/herinschrijven', [InschrijvingActiesController::class, 'kiesHerinschrijven'])->name('herinschrijven');
        Route::get('/studenten/{student}/herinschrijven', [InschrijvingActiesController::class, 'herinschrijvenForm'])->name('herinschrijven.form');
        Route::post('/studenten/{student}/herinschrijven', [InschrijvingActiesController::class, 'herinschrijven'])->name('herinschrijven.store');

        // Uitschrijven
        Route::get('/uitschrijven', [InschrijvingActiesController::class, 'kiesUitschrijven'])->name('uitschrijven');
        Route::get('/studenten/{student}/uitschrijven', [InschrijvingActiesController::class, 'uitschrijvenForm'])->name('uitschrijven.form');
        Route::post('/studenten/{student}/uitschrijven', [InschrijvingActiesController::class, 'uitschrijven'])->name('uitschrijven.store');

        // Verklaringen (A4)
        Route::get('/verklaringen', [VerklaringController::class, 'index'])->name('verklaringen');

        // Rapporten (SZ: geen cijferkolom)
        Route::get('/rapporten', [RapportController::class, 'index'])->name('rapporten');
        Route::get('/rapporten/klassenlijst', [RapportController::class, 'klassenlijst'])->name('rapporten.klassenlijst');
    });

    // --- Docent — eigen vak ---
    Route::middleware('rol:docent')->group(function () {
        Route::view('/mijn-vakken', 'placeholder')->name('mijn-vakken');
        Route::view('/cijferinvoer', 'placeholder')->name('cijferinvoer');
    });

    // --- Cijferinzage & rapporten — Examencommissie & Directie ---
    Route::middleware('rol:examencommissie,directie')->group(function () {
        Route::view('/cijferoverzicht', 'placeholder')->name('cijferoverzicht');
        Route::get('/rapporten-inzage', [RapportController::class, 'index'])->name('rapporten.inzage');
    });

    // --- Alumni-rapport — Studentenzaken & Directie ---
    Route::middleware('rol:studentenzaken,directie')->group(function () {
        Route::get('/rapporten/alumni', [RapportController::class, 'alumni'])->name('rapporten.alumni');
    });

    // --- Beheer — Beheerder ---
    Route::middleware('rol:beheerder')->group(function () {
        Route::get('/gebruikers', [GebruikerController::class, 'index'])->name('gebruikers');
        Route::put('/gebruikers/{gebruiker}/rol', [GebruikerController::class, 'updateRol'])->name('gebruikers.rol');

        Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log');

        // Opzoektabellen (generieke referentie-CRUD)
        Route::get('/opzoektabellen', [ReferentieController::class, 'index'])->name('opzoektabellen');
        Route::get('/opzoektabellen/{tabel}/nieuw', [ReferentieController::class, 'create'])->name('opzoektabellen.create');
        Route::post('/opzoektabellen/{tabel}', [ReferentieController::class, 'store'])->name('opzoektabellen.store');
        Route::get('/opzoektabellen/{tabel}/{id}/bewerken', [ReferentieController::class, 'edit'])->name('opzoektabellen.edit');
        Route::put('/opzoektabellen/{tabel}/{id}', [ReferentieController::class, 'update'])->name('opzoektabellen.update');
        Route::delete('/opzoektabellen/{tabel}/{id}', [ReferentieController::class, 'destroy'])->name('opzoektabellen.destroy');
        Route::get('/opzoektabellen/{tabel}', [ReferentieController::class, 'index'])->name('opzoektabellen.tabel');
    });
});
