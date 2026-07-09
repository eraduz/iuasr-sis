<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\DevLoginController;
use App\Http\Controllers\BetalingController;
use App\Http\Controllers\BulkInschrijvingController;
use App\Http\Controllers\CijferController;
use App\Http\Controllers\CollegegeldController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GebruikerController;
use App\Http\Controllers\InschrijvingActiesController;
use App\Http\Controllers\InschrijvingController;
use App\Http\Controllers\OndertekeningController;
use App\Http\Controllers\RapportController;
use App\Http\Controllers\ReferentieController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentDocumentController;
use App\Http\Controllers\VakstructuurController;
use App\Http\Controllers\VaktoewijzingController;
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

// Publieke echtheidscontrole van ondertekende documenten (geen login vereist).
Route::match(['get', 'post'], '/verificatie', [OndertekeningController::class, 'verificatie'])->name('verificatie');

Route::middleware('auth')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // PDF-handleidingen: medewerkers (iedereen) en technisch/herstel (Beheerder).
    Route::get('/handleiding', [App\Http\Controllers\HandleidingController::class, 'medewerkers'])->name('handleiding.medewerkers');
    Route::get('/handleiding/technisch', [App\Http\Controllers\HandleidingController::class, 'technisch'])->name('handleiding.technisch');

    // Digitaal ondertekende documenten — archief/log (Beheerder, Schoolbestuur, Directie, Studentenzaken)
    Route::middleware('rol:beheerder,bestuur,directie,studentenzaken')->group(function () {
        Route::get('/ondertekende-documenten', [OndertekeningController::class, 'index'])->name('ondertekening');
        // Eigen PDF uploaden en laten waarmerken.
        Route::get('/ondertekende-documenten/uploaden', [OndertekeningController::class, 'uploadForm'])->name('ondertekening.uploaden');
        Route::post('/ondertekende-documenten/ondertekenen', [OndertekeningController::class, 'onderteken'])->name('ondertekening.onderteken');
        Route::get('/ondertekende-documenten/{document}/klaar', [OndertekeningController::class, 'klaar'])->name('ondertekening.klaar');
        Route::get('/ondertekende-documenten/{document}/download', [OndertekeningController::class, 'download'])->name('ondertekening.download');
        Route::get('/ondertekende-documenten/{document}/waarmerk', [OndertekeningController::class, 'downloadWaarmerk'])->name('ondertekening.waarmerk');
    });

    // Actieve-studenten Excel-export (incl. IBAN, zonder BSN) — Studentenzaken, Financiën, Beheer
    Route::middleware('rol:studentenzaken,financien,beheerder')->group(function () {
        Route::get('/rapporten/actieve-studenten.xlsx', [RapportController::class, 'actieveStudentenExport'])->name('rapporten.actieve-studenten');
    });

    // --- Studenten inzien: SZ, Beheerder, Examencommissie, Directie ---
    Route::middleware('rol:studentenzaken,beheerder,examencommissie,directie,bestuur')->group(function () {
        Route::get('/studenten', [StudentController::class, 'index'])->name('studenten.index');
        Route::get('/studenten/{student}', [StudentController::class, 'show'])->name('studenten.show');
    });
    // BSN-inzage NIET voor Schoolbestuur (extra gevoelig).
    Route::middleware('rol:studentenzaken,beheerder,examencommissie,directie')->group(function () {
        Route::get('/studenten/{student}/bsn', [StudentController::class, 'bsn'])->name('studenten.bsn');
    });

    // --- Identiteit & inschrijving beheren: SZ, Beheerder ---
    Route::middleware('rol:studentenzaken,beheerder')->group(function () {
        // Vrijstellingsbesluit van de examencommissie verwerken (één klik).
        Route::post('/vrijstellingsbesluiten/{besluit}/verwerken', [App\Http\Controllers\VrijstellingsbesluitController::class, 'verwerk'])->name('vrijstellingsbesluiten.verwerken');

        // Inschrijven
        Route::get('/inschrijven', [InschrijvingController::class, 'create'])->name('inschrijven');
        Route::post('/inschrijven', [InschrijvingController::class, 'store'])->name('inschrijven.store');

        // Bulk-inschrijven (CSV-export van het aanmeldportaal) — controle -> bevestigen
        Route::get('/bulk-inschrijven', [BulkInschrijvingController::class, 'form'])->name('bulk-inschrijven');
        Route::get('/bulk-inschrijven/sjabloon', [BulkInschrijvingController::class, 'sjabloon'])->name('bulk-inschrijven.sjabloon');
        Route::post('/bulk-inschrijven/controle', [BulkInschrijvingController::class, 'controle'])->name('bulk-inschrijven.controle');
        Route::post('/bulk-inschrijven', [BulkInschrijvingController::class, 'importeer'])->name('bulk-inschrijven.importeer');

        // Muteren
        Route::get('/studenten/{student}/muteren', [StudentController::class, 'edit'])->name('studenten.edit');
        Route::put('/studenten/{student}', [StudentController::class, 'update'])->name('studenten.update');

        // Schorsen (één klik, omkeerbaar)
        Route::post('/studenten/{student}/schorsen', [InschrijvingActiesController::class, 'schors'])->name('studenten.schors');

        // Interne notities per student
        Route::post('/studenten/{student}/notities', [StudentController::class, 'notitieStore'])->name('studenten.notities.store');
        Route::delete('/studenten/{student}/notities/{notitie}', [StudentController::class, 'notitieDestroy'])->name('studenten.notities.destroy');

        // Documenten per student (identiteitsbewijs, diploma, cijferlijst, pasfoto, ...)
        Route::post('/studenten/{student}/documenten', [StudentDocumentController::class, 'upload'])->name('studenten.documenten.upload');
        Route::get('/documenten/{document}', [StudentDocumentController::class, 'download'])->name('documenten.download');
        Route::delete('/documenten/{document}', [StudentDocumentController::class, 'destroy'])->name('documenten.destroy');
        Route::post('/studenten/{student}/documenten-later', [StudentDocumentController::class, 'later'])->name('studenten.documenten.later');

        // Landelijke kennistoetsen (PABO) registreren
        Route::post('/studenten/{student}/kennistoetsen', [App\Http\Controllers\KennistoetsController::class, 'bijwerken'])->name('studenten.kennistoetsen.bijwerken');

        // Vrijstellingen (administratief; SZ registreert het examencommissie-besluit)
        Route::post('/studenten/{student}/vrijstellingen', [App\Http\Controllers\VrijstellingController::class, 'store'])->name('studenten.vrijstellingen.store');
        Route::delete('/studenten/{student}/vrijstellingen/{vaktoewijzing}', [App\Http\Controllers\VrijstellingController::class, 'destroy'])->name('studenten.vrijstellingen.destroy');

        // Herinschrijven
        Route::get('/herinschrijven', [InschrijvingActiesController::class, 'kiesHerinschrijven'])->name('herinschrijven');
        Route::get('/studenten/{student}/herinschrijven', [InschrijvingActiesController::class, 'herinschrijvenForm'])->name('herinschrijven.form');
        Route::post('/studenten/{student}/herinschrijven', [InschrijvingActiesController::class, 'herinschrijven'])->name('herinschrijven.store');

        // Uitschrijven
        Route::get('/uitschrijven', [InschrijvingActiesController::class, 'kiesUitschrijven'])->name('uitschrijven');
        Route::get('/studenten/{student}/uitschrijven', [InschrijvingActiesController::class, 'uitschrijvenForm'])->name('uitschrijven.form');
        Route::post('/studenten/{student}/uitschrijven', [InschrijvingActiesController::class, 'uitschrijven'])->name('uitschrijven.store');

        // Verklaringen (A4) — preview + ondertekende PDF genereren
        Route::get('/verklaringen', [VerklaringController::class, 'index'])->name('verklaringen');
        Route::post('/verklaringen/genereer', [VerklaringController::class, 'genereer'])->name('verklaringen.genereer');

        // Rapporten (SZ: geen cijferkolom)
        Route::get('/rapporten', [RapportController::class, 'index'])->name('rapporten');
        Route::get('/rapporten/klassenlijst', [RapportController::class, 'klassenlijst'])->name('rapporten.klassenlijst');

        // Collegegeld instellen (jaarlijks) — Studentenadministratie
        Route::get('/collegegeld', [CollegegeldController::class, 'index'])->name('collegegeld');
        Route::post('/collegegeld', [CollegegeldController::class, 'store'])->name('collegegeld.store');
        Route::delete('/collegegeld/{tarief}', [CollegegeldController::class, 'destroy'])->name('collegegeld.destroy');

        // Vakstructuur (curriculum) — per studiejaar/periode vakken beheren
        Route::get('/vakstructuur', [VakstructuurController::class, 'index'])->name('vakstructuur');
        Route::post('/vakstructuur', [VakstructuurController::class, 'store'])->name('vakstructuur.store');
        Route::get('/vakstructuur/{vak}/bewerken', [VakstructuurController::class, 'edit'])->name('vakstructuur.edit');
        Route::put('/vakstructuur/{vak}', [VakstructuurController::class, 'update'])->name('vakstructuur.update');
        Route::delete('/vakstructuur/{vak}', [VakstructuurController::class, 'destroy'])->name('vakstructuur.destroy');

        // Vaktoewijzing per student aanpassen
        Route::get('/inschrijvingen/{inschrijving}/vakken', [VaktoewijzingController::class, 'edit'])->name('inschrijving.vakken');
        Route::put('/inschrijvingen/{inschrijving}/vakken', [VaktoewijzingController::class, 'update'])->name('inschrijving.vakken.update');
    });

    // --- Financiële Administratie: betalingen & achterstanden ---
    Route::middleware('rol:financien,beheerder')->group(function () {
        Route::get('/financien', [BetalingController::class, 'index'])->name('financien');
        // Bulk-import van betalingen (vóór de {student}-route i.v.m. matching).
        Route::get('/financien/import/sjabloon', [BetalingController::class, 'importSjabloon'])->name('financien.import.sjabloon');
        Route::post('/financien/import/controle', [BetalingController::class, 'importControle'])->name('financien.import.controle');
        Route::post('/financien/import', [BetalingController::class, 'import'])->name('financien.import');
        Route::get('/financien/{student}', [BetalingController::class, 'student'])->name('financien.student');
        Route::post('/financien/{student}/betaling', [BetalingController::class, 'registreer'])->name('financien.betaling');
    });

    // --- Docent — eigen vak ---
    Route::middleware('rol:docent')->group(function () {
        Route::get('/mijn-vakken', [CijferController::class, 'mijnVakken'])->name('mijn-vakken');
        Route::get('/cijferinvoer', fn () => redirect()->route('mijn-vakken'))->name('cijferinvoer');
    });

    // --- Cijferinvoer/-inzage per vak (docent eigen vak; examencie/directie inzage) ---
    Route::middleware('rol:docent,examencommissie,directie')->group(function () {
        Route::get('/vakken/{vak}/cijfers', [CijferController::class, 'invoer'])->name('vakken.cijfers');
        // Tentamenlijst per vak (read-only overzicht + ondertekende PDF).
        Route::get('/vakken/{vak}/tentamenlijst', [CijferController::class, 'tentamenlijst'])->name('vakken.tentamenlijst');
        Route::post('/vakken/{vak}/tentamenlijst/pdf', [CijferController::class, 'tentamenlijstPdf'])->name('vakken.tentamenlijst.pdf');
    });
    // Opslaan: docent (concept) of examencommissie (correctie na indienen/vaststellen).
    Route::middleware('rol:docent,examencommissie')->group(function () {
        Route::post('/vakken/{vak}/cijfers', [CijferController::class, 'opslaan'])->name('vakken.cijfers.opslaan');
    });
    // Vaststellingsworkflow.
    Route::middleware('rol:docent')->group(function () {
        Route::post('/vakken/{vak}/indienen', [CijferController::class, 'indienen'])->name('vakken.cijfers.indienen');
    });
    Route::middleware('rol:examencommissie')->group(function () {
        Route::post('/vakken/{vak}/vaststellen', [CijferController::class, 'vaststellen'])->name('vakken.cijfers.vaststellen');
        Route::post('/vakken/{vak}/terugsturen', [CijferController::class, 'terugsturen'])->name('vakken.cijfers.terugsturen');
    });

    // --- Cijferinzage & rapporten — Examencommissie & Directie ---
    Route::middleware('rol:examencommissie,directie')->group(function () {
        Route::get('/cijferoverzicht', [CijferController::class, 'overzicht'])->name('cijferoverzicht');
        Route::get('/rapporten-inzage', [RapportController::class, 'index'])->name('rapporten.inzage');
        // Leerjaar-herbeoordeling / overgangsadvies (EC t.o.v. drempel).
        Route::get('/overgang', [RapportController::class, 'overgang'])->name('overgang');
        // Cijferlijst / transcript per student (+ ondertekende PDF).
        Route::get('/cijferlijst', [RapportController::class, 'cijferlijst'])->name('cijferlijst');
        Route::post('/cijferlijst/{student}/pdf', [RapportController::class, 'cijferlijstPdf'])->name('cijferlijst.pdf');
        // EC-rapport (studievoortgang per opleiding/klas).
        Route::get('/ec-rapport', [RapportController::class, 'ecRapport'])->name('ec-rapport');
        // Definitieve resultaten per e-mail naar studenten (per opleiding).
        Route::get('/resultaten-mailen', [App\Http\Controllers\ResultatenMailController::class, 'overzicht'])->name('resultaten-mailen');
        Route::post('/resultaten-mailen', [App\Http\Controllers\ResultatenMailController::class, 'versturen'])->name('resultaten-mailen.versturen');
        // Vrijstellingsbesluit vastleggen en naar Studentenzaken sturen.
        Route::post('/studenten/{student}/vrijstellingsbesluiten', [App\Http\Controllers\VrijstellingsbesluitController::class, 'store'])->name('vrijstellingsbesluiten.store');
        Route::post('/vrijstellingsbesluiten/{besluit}/annuleren', [App\Http\Controllers\VrijstellingsbesluitController::class, 'annuleer'])->name('vrijstellingsbesluiten.annuleren');
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

        // Student VOLLEDIG verwijderen (foutieve records) — uitsluitend Beheerder.
        Route::delete('/studenten/{student}', [StudentController::class, 'destroy'])->name('studenten.destroy');

        // Recovery-backup (versleutelde ZIP met database, applicatie en bestanden)
        Route::get('/beheer/backup', [App\Http\Controllers\BackupController::class, 'index'])->name('backup');
        Route::post('/beheer/backup', [App\Http\Controllers\BackupController::class, 'download'])->name('backup.download');

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
