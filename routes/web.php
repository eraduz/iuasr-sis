<?php

use App\Http\Controllers\AanwezigheidsregelingController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\DevLoginController;
use App\Http\Controllers\BetaalregelingController;
use App\Http\Controllers\BetalingController;
use App\Http\Controllers\BetalingsafspraakController;
use App\Http\Controllers\BulkInschrijvingController;
use App\Http\Controllers\CijferController;
use App\Http\Controllers\CollegegeldController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GebruikerController;
use App\Http\Controllers\InschrijvingActiesController;
use App\Http\Controllers\InschrijvingController;
use App\Http\Controllers\KortingController;
use App\Http\Controllers\OndertekeningController;
use App\Http\Controllers\PresentieController;
use App\Http\Controllers\RapportController;
use App\Http\Controllers\ReferentieController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentDocumentController;
use App\Http\Controllers\TaakController;
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

    // Keuzescherm na de login: welke module wil de gebruiker gebruiken?
    Route::get('/modules', [App\Http\Controllers\ModuleController::class, 'index'])->name('modules.kiezen');

    /*
    |--------------------------------------------------------------------------
    | Module: Cursussen Administratie
    |--------------------------------------------------------------------------
    | Rolverdeling binnen de module:
    |  - Cursusadministratie = cursusdirecteur, GESCOPED op de eigen cursus(sen):
    |    ziet/beheert alleen die cursussen, cursisten en inschrijvingen.
    |  - Financiële Administratie (boekhouding): cursusgelden van ALLE cursussen.
    |  - Beheerder: alles, incl. cursus aanmaken/verwijderen en directeur toewijzen.
    |  - Schoolbestuur: dashboard/statistieken en cursistinzage (alleen-lezen).
    | De scoping wordt server-side afgedwongen in de controllers (zichtbaarVoor).
    */
    // Dashboard en rapportage: alle rollen met toegang tot de module. De
    // cursusdirecteur ziet uitsluitend de eigen cursus(sen) (server-side gescoped).
    Route::middleware('rol:cursusadministratie,financien,beheerder,bestuur')->prefix('cursussen')->group(function () {
        Route::get('/', [App\Http\Controllers\Cursus\CursusDashboardController::class, 'index'])->name('cursussen.dashboard');
        Route::get('/rapport', [App\Http\Controllers\Cursus\CursusrapportController::class, 'index'])->name('cursussen.rapport');
        Route::get('/rapport/export.csv', [App\Http\Controllers\Cursus\CursusrapportController::class, 'export'])->name('cursussen.rapport.export');
        // Startpagina van één cursus (directe ingang vanaf het welkomstscherm).
        Route::get('/cursus/{cursus}', [App\Http\Controllers\Cursus\CursusDashboardController::class, 'cursus'])->name('cursussen.cursus');
    });

    // Boekhouding: cursusgelden volgen en betalingen registreren/corrigeren (alle cursussen).
    Route::middleware('rol:financien,beheerder')->prefix('cursussen')->group(function () {
        Route::get('/betalingen', [App\Http\Controllers\Cursus\CursusbetalingController::class, 'overzicht'])->name('cursussen.betalingen');
        Route::post('/inschrijvingen/{inschrijving}/betalingen', [App\Http\Controllers\Cursus\CursusbetalingController::class, 'registreer'])->name('cursussen.betaling.registreer');
        Route::put('/betalingen/{betaling}', [App\Http\Controllers\Cursus\CursusbetalingController::class, 'bijwerken'])->name('cursussen.betaling.bijwerken');
        Route::delete('/betalingen/{betaling}', [App\Http\Controllers\Cursus\CursusbetalingController::class, 'verwijderen'])->name('cursussen.betaling.verwijderen');
    });

    // Cursus aanmaken, kopiëren, verwijderen en directeur toewijzen: uitsluitend de Beheerder.
    Route::middleware('rol:beheerder')->prefix('cursussen')->group(function () {
        Route::get('/beheer/nieuw', [App\Http\Controllers\Cursus\CursusController::class, 'create'])->name('cursussen.create');
        Route::post('/beheer', [App\Http\Controllers\Cursus\CursusController::class, 'store'])->name('cursussen.store');
        Route::get('/beheer/{bron}/kopieren', [App\Http\Controllers\Cursus\CursusController::class, 'kopieForm'])->name('cursussen.kopieren');
        Route::delete('/beheer/{cursus}', [App\Http\Controllers\Cursus\CursusController::class, 'destroy'])->name('cursussen.destroy');
    });

    // Cursusbeheer (details) en cursisten/inschrijvingen beheren: cursusdirecteur
    // (gescoped) en Beheer. De literal- en import-routes staan bewust vóór de
    // {cursist}-route zodat 'nieuw' en 'import' niet als cursist worden gelezen.
    Route::middleware('rol:cursusadministratie,beheerder')->prefix('cursussen')->group(function () {
        Route::get('/beheer', [App\Http\Controllers\Cursus\CursusController::class, 'index'])->name('cursussen.beheer');
        Route::get('/beheer/{cursus}/bewerken', [App\Http\Controllers\Cursus\CursusController::class, 'edit'])->name('cursussen.edit');
        Route::put('/beheer/{cursus}', [App\Http\Controllers\Cursus\CursusController::class, 'update'])->name('cursussen.update');

        Route::get('/cursisten/nieuw', [App\Http\Controllers\Cursus\CursistController::class, 'create'])->name('cursisten.create');
        Route::post('/cursisten', [App\Http\Controllers\Cursus\CursistController::class, 'store'])->name('cursisten.store');
        Route::get('/cursisten/import/sjabloon', [App\Http\Controllers\Cursus\CursistController::class, 'importSjabloon'])->name('cursisten.import.sjabloon');
        Route::post('/cursisten/import/controle', [App\Http\Controllers\Cursus\CursistController::class, 'importControle'])->name('cursisten.import.controle');
        Route::post('/cursisten/import', [App\Http\Controllers\Cursus\CursistController::class, 'import'])->name('cursisten.import');
        Route::get('/cursisten/{cursist}/bewerken', [App\Http\Controllers\Cursus\CursistController::class, 'edit'])->name('cursisten.edit');
        Route::put('/cursisten/{cursist}', [App\Http\Controllers\Cursus\CursistController::class, 'update'])->name('cursisten.update');

        Route::post('/cursisten/{cursist}/inschrijven', [App\Http\Controllers\Cursus\CursusinschrijvingController::class, 'store'])->name('cursisten.inschrijven');
        Route::put('/cursisten/{cursist}/inschrijvingen/{inschrijving}', [App\Http\Controllers\Cursus\CursusinschrijvingController::class, 'update'])->name('cursisten.inschrijving.update');
    });

    // Cursisteninzage: cursusdirecteur (gescoped), Beheer én Schoolbestuur (alleen-lezen).
    Route::middleware('rol:cursusadministratie,beheerder,bestuur')->prefix('cursussen')->group(function () {
        Route::get('/cursisten', [App\Http\Controllers\Cursus\CursistController::class, 'index'])->name('cursisten');
        Route::get('/cursisten/{cursist}', [App\Http\Controllers\Cursus\CursistController::class, 'show'])->name('cursisten.show');
    });

    /*
    |--------------------------------------------------------------------------
    | Module: Relatiebeheer & Stagebeheer
    |--------------------------------------------------------------------------
    | Opleidingoverstijgend (PABO, Bachelor Islamitische Theologie, Master IGV)
    | en opleidinggebonden gescoped. Rolverdeling:
    |  - Relatiebeheerder / Stagecoördinator: beheren organisaties (en later
    |    contactpersonen, stages) van de eigen opleiding(en).
    |  - Directie (opleidingsmanager) & Schoolbestuur: inzage (alleen-lezen).
    |  - Beheerder: alles.
    | De scoping wordt server-side afgedwongen (zichtbaarVoor / beheerbaarVoor).
    | De beheer-routes staan bewust vóór de inzage-routes zodat '/organisaties/
    | nieuw' niet als een organisatie-id wordt gelezen.
    */
    Route::middleware('rol:relatiebeheerder,stagecoordinator,beheerder')->prefix('relatiebeheer')->group(function () {
        Route::get('/organisaties/nieuw', [App\Http\Controllers\Relatie\OrganisatieController::class, 'create'])->name('relaties.create');
        Route::post('/organisaties', [App\Http\Controllers\Relatie\OrganisatieController::class, 'store'])->name('relaties.store');
        Route::get('/organisaties/{organisatie}/bewerken', [App\Http\Controllers\Relatie\OrganisatieController::class, 'edit'])->name('relaties.edit');
        Route::put('/organisaties/{organisatie}', [App\Http\Controllers\Relatie\OrganisatieController::class, 'update'])->name('relaties.update');
        Route::post('/organisaties/{organisatie}/status', [App\Http\Controllers\Relatie\OrganisatieController::class, 'status'])->name('relaties.status');
    });

    Route::middleware('rol:relatiebeheerder,stagecoordinator,directie,bestuur,beheerder')->prefix('relatiebeheer')->group(function () {
        Route::get('/', [App\Http\Controllers\Relatie\OrganisatieController::class, 'index'])->name('relaties');
        Route::get('/organisaties/{organisatie}', [App\Http\Controllers\Relatie\OrganisatieController::class, 'show'])->name('relaties.show');
    });

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Globale bestuurspagina — instellingsbreed overzicht (Schoolbestuur en Beheer).
    Route::middleware('rol:bestuur,beheerder')->group(function () {
        Route::get('/bestuur', [App\Http\Controllers\BestuurController::class, 'index'])->name('bestuur');
    });

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

        // 50%-aanwezigheidsregeling toekennen/intrekken (met toestemming directie).
        Route::post('/inschrijvingen/{inschrijving}/aanwezigheidsregeling', [AanwezigheidsregelingController::class, 'bijwerken'])->name('inschrijving.aanwezigheidsregeling');

        // Betaalregeling: vijf termijnen of één factuur voor het volledige jaarbedrag.
        Route::post('/inschrijvingen/{inschrijving}/betaalregeling', [BetaalregelingController::class, 'bijwerken'])->name('inschrijving.betaalregeling');

        // Korting op het collegegeld van deze opleiding (bv. tweede opleiding).
        Route::post('/inschrijvingen/{inschrijving}/korting', [KortingController::class, 'bijwerken'])->name('inschrijving.korting');

        // Gedeelde takenlijst van Studentenzaken (naar het model van Outlook Taken).
        Route::get('/taken', [TaakController::class, 'index'])->name('taken');
        Route::post('/taken', [TaakController::class, 'store'])->name('taken.store');
        Route::put('/taken/{taak}', [TaakController::class, 'update'])->name('taken.update');
        Route::post('/taken/{taak}/afronden', [TaakController::class, 'afronden'])->name('taken.afronden');
        Route::delete('/taken/{taak}', [TaakController::class, 'destroy'])->name('taken.destroy');
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
        // Corrigeren en verwijderen van een geboekte betaling (beide gelogd).
        Route::put('/financien/{student}/betaling/{betaling}', [BetalingController::class, 'bijwerken'])->name('financien.betaling.bijwerken');
        Route::delete('/financien/{student}/betaling/{betaling}', [BetalingController::class, 'verwijderen'])->name('financien.betaling.verwijderen');

        // Betalingsafspraak: heft de blokkades op zolang zij loopt (gelogd).
        Route::post('/financien/{student}/afspraak', [BetalingsafspraakController::class, 'vastleggen'])->name('financien.afspraak');
        Route::post('/financien/{student}/afspraak/{afspraak}/intrekken', [BetalingsafspraakController::class, 'intrekken'])->name('financien.afspraak.intrekken');
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

    // --- Presentie (aanwezigheidsregistratie per college) ---
    // Inzage: docent (eigen vak), examencommissie, directie (eigen opleiding), bestuur.
    Route::middleware('rol:docent,examencommissie,directie,bestuur')->group(function () {
        Route::get('/vakken/{vak}/presentie', [PresentieController::class, 'lijst'])->name('vakken.presentie');
        Route::get('/presentieoverzicht', [PresentieController::class, 'overzicht'])->name('presentieoverzicht');
    });
    // Registreren: uitsluitend de docent van het eigen vak (verplicht).
    Route::middleware('rol:docent')->group(function () {
        Route::post('/vakken/{vak}/presentie', [PresentieController::class, 'opslaan'])->name('vakken.presentie.opslaan');
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

    // --- Alumni-rapport — Studentenzaken, Examencommissie, Directie & Schoolbestuur ---
    // Bevat geen cijfers en geen BSN; Directie ziet alleen de eigen opleiding(en),
    // de overige rollen alle alumni.
    Route::middleware('rol:studentenzaken,examencommissie,directie,bestuur')->group(function () {
        Route::get('/rapporten/alumni', [RapportController::class, 'alumni'])->name('rapporten.alumni');
    });

    // --- Beheer — Beheerder ---
    Route::middleware('rol:beheerder')->group(function () {
        Route::get('/gebruikers', [GebruikerController::class, 'index'])->name('gebruikers');
        Route::put('/gebruikers/{gebruiker}/rol', [GebruikerController::class, 'updateRol'])->name('gebruikers.rol');
        // Opleidingtoewijzing voor directieleden (zichtbaarheid per opleiding).
        Route::put('/gebruikers/{gebruiker}/opleidingen', [GebruikerController::class, 'updateOpleidingen'])->name('gebruikers.opleidingen');

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
