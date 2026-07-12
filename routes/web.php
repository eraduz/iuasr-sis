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
| Webroutes — IUASR Management Systeem
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

        // Contactpersonen bij een organisatie (Fase B).
        Route::get('/organisaties/{organisatie}/contactpersonen/nieuw', [App\Http\Controllers\Relatie\ContactpersoonController::class, 'create'])->name('contactpersonen.create');
        Route::post('/organisaties/{organisatie}/contactpersonen', [App\Http\Controllers\Relatie\ContactpersoonController::class, 'store'])->name('contactpersonen.store');
        Route::get('/contactpersonen/{contactpersoon}/bewerken', [App\Http\Controllers\Relatie\ContactpersoonController::class, 'edit'])->name('contactpersonen.edit');
        Route::put('/contactpersonen/{contactpersoon}', [App\Http\Controllers\Relatie\ContactpersoonController::class, 'update'])->name('contactpersonen.update');
        Route::post('/contactpersonen/{contactpersoon}/status', [App\Http\Controllers\Relatie\ContactpersoonController::class, 'status'])->name('contactpersonen.status');

        // Contactmomenten en notities bij een organisatie (Fase C).
        Route::get('/organisaties/{organisatie}/contactmomenten/nieuw', [App\Http\Controllers\Relatie\ContactmomentController::class, 'create'])->name('contactmomenten.create');
        Route::post('/organisaties/{organisatie}/contactmomenten', [App\Http\Controllers\Relatie\ContactmomentController::class, 'store'])->name('contactmomenten.store');
        Route::get('/contactmomenten/{contactmoment}/bewerken', [App\Http\Controllers\Relatie\ContactmomentController::class, 'edit'])->name('contactmomenten.edit');
        Route::put('/contactmomenten/{contactmoment}', [App\Http\Controllers\Relatie\ContactmomentController::class, 'update'])->name('contactmomenten.update');
        // Actiepunt -> taak: maak een opvolgtaak van een contactmoment (Fase H).
        Route::post('/contactmomenten/{contactmoment}/taak', [App\Http\Controllers\Relatie\ContactmomentController::class, 'maakTaak'])->name('contactmomenten.taak');

        Route::post('/organisaties/{organisatie}/notities', [App\Http\Controllers\Relatie\RelatieNotitieController::class, 'store'])->name('relaties.notities.store');
        Route::delete('/relatie-notities/{notitie}', [App\Http\Controllers\Relatie\RelatieNotitieController::class, 'destroy'])->name('relaties.notities.destroy');
    });

    // Stagebeheer: stageplaatsen (aanbod) en stages (plaatsingen). Muteren is
    // voorbehouden aan de stagecoördinator (eigen opleiding) en de Beheerder;
    // inzage staat in de bredere module-inzagegroep hieronder (Fase D).
    Route::middleware('rol:stagecoordinator,beheerder')->prefix('relatiebeheer')->group(function () {
        Route::get('/organisaties/{organisatie}/stageplaatsen/nieuw', [App\Http\Controllers\Relatie\StageplaatsController::class, 'create'])->name('stageplaatsen.create');
        Route::post('/organisaties/{organisatie}/stageplaatsen', [App\Http\Controllers\Relatie\StageplaatsController::class, 'store'])->name('stageplaatsen.store');
        Route::get('/stageplaatsen/{stageplaats}/bewerken', [App\Http\Controllers\Relatie\StageplaatsController::class, 'edit'])->name('stageplaatsen.edit');
        Route::put('/stageplaatsen/{stageplaats}', [App\Http\Controllers\Relatie\StageplaatsController::class, 'update'])->name('stageplaatsen.update');
        Route::post('/stageplaatsen/{stageplaats}/status', [App\Http\Controllers\Relatie\StageplaatsController::class, 'status'])->name('stageplaatsen.status');

        Route::get('/organisaties/{organisatie}/stages/nieuw', [App\Http\Controllers\Relatie\StageController::class, 'create'])->name('stages.create');
        Route::post('/organisaties/{organisatie}/stages', [App\Http\Controllers\Relatie\StageController::class, 'store'])->name('stages.store');
        Route::get('/stages/{stage}/bewerken', [App\Http\Controllers\Relatie\StageController::class, 'edit'])->name('stages.edit');
        Route::put('/stages/{stage}', [App\Http\Controllers\Relatie\StageController::class, 'update'])->name('stages.update');
    });

    // Taken en agenda-afspraken bij een organisatie (Fase E). Muteren door wie de
    // organisatie beheert (relatiebeheerder/stagecoördinator eigen opleiding + Beheer).
    Route::middleware('rol:relatiebeheerder,stagecoordinator,beheerder')->prefix('relatiebeheer')->group(function () {
        Route::post('/organisaties/{organisatie}/taken', [App\Http\Controllers\Relatie\RelatietaakController::class, 'store'])->name('relatietaken.store');
        Route::get('/taken/{taak}/bewerken', [App\Http\Controllers\Relatie\RelatietaakController::class, 'edit'])->name('relatietaken.edit');
        Route::put('/taken/{taak}', [App\Http\Controllers\Relatie\RelatietaakController::class, 'update'])->name('relatietaken.update');
        Route::post('/taken/{taak}/afronden', [App\Http\Controllers\Relatie\RelatietaakController::class, 'afronden'])->name('relatietaken.afronden');
        Route::delete('/taken/{taak}', [App\Http\Controllers\Relatie\RelatietaakController::class, 'destroy'])->name('relatietaken.destroy');

        Route::get('/organisaties/{organisatie}/afspraken/nieuw', [App\Http\Controllers\Relatie\AfspraakController::class, 'create'])->name('afspraken.create');
        Route::post('/organisaties/{organisatie}/afspraken', [App\Http\Controllers\Relatie\AfspraakController::class, 'store'])->name('afspraken.store');
        Route::get('/afspraken/{afspraak}/bewerken', [App\Http\Controllers\Relatie\AfspraakController::class, 'edit'])->name('afspraken.edit');
        Route::put('/afspraken/{afspraak}', [App\Http\Controllers\Relatie\AfspraakController::class, 'update'])->name('afspraken.update');
        Route::delete('/afspraken/{afspraak}', [App\Http\Controllers\Relatie\AfspraakController::class, 'destroy'])->name('afspraken.destroy');

        // Documenten (versiebeheer) en overeenkomsten (Fase F).
        Route::post('/organisaties/{organisatie}/documenten', [App\Http\Controllers\Relatie\RelatieDocumentController::class, 'store'])->name('relatiedocumenten.store');
        Route::post('/relatie-documenten/{document}/versie', [App\Http\Controllers\Relatie\RelatieDocumentController::class, 'versie'])->name('relatiedocumenten.versie');
        Route::delete('/relatie-documenten/{document}', [App\Http\Controllers\Relatie\RelatieDocumentController::class, 'destroy'])->name('relatiedocumenten.destroy');

        Route::get('/organisaties/{organisatie}/overeenkomsten/nieuw', [App\Http\Controllers\Relatie\OvereenkomstController::class, 'create'])->name('overeenkomsten.create');
        Route::post('/organisaties/{organisatie}/overeenkomsten', [App\Http\Controllers\Relatie\OvereenkomstController::class, 'store'])->name('overeenkomsten.store');
        Route::get('/overeenkomsten/{overeenkomst}/bewerken', [App\Http\Controllers\Relatie\OvereenkomstController::class, 'edit'])->name('overeenkomsten.edit');
        Route::put('/overeenkomsten/{overeenkomst}', [App\Http\Controllers\Relatie\OvereenkomstController::class, 'update'])->name('overeenkomsten.update');
        Route::delete('/overeenkomsten/{overeenkomst}', [App\Http\Controllers\Relatie\OvereenkomstController::class, 'destroy'])->name('overeenkomsten.destroy');
    });

    Route::middleware('rol:relatiebeheerder,stagecoordinator,directie,bestuur,beheerder')->prefix('relatiebeheer')->group(function () {
        Route::get('/', [App\Http\Controllers\Relatie\RelatieDashboardController::class, 'index'])->name('relatiebeheer.dashboard');
        Route::get('/rapport', [App\Http\Controllers\Relatie\RelatieDashboardController::class, 'rapport'])->name('relatiebeheer.rapport');
        Route::get('/rapport/export.csv', [App\Http\Controllers\Relatie\RelatieDashboardController::class, 'export'])->name('relatiebeheer.rapport.export');
        Route::get('/organisaties', [App\Http\Controllers\Relatie\OrganisatieController::class, 'index'])->name('relaties');
        Route::get('/stages', [App\Http\Controllers\Relatie\StageController::class, 'index'])->name('stages');
        Route::get('/agenda', [App\Http\Controllers\Relatie\AfspraakController::class, 'index'])->name('agenda');
        Route::get('/agenda.ics', [App\Http\Controllers\Relatie\AfspraakController::class, 'ical'])->name('relatiebeheer.agenda.ics');
        Route::get('/zoeken', [App\Http\Controllers\Relatie\ZoekController::class, 'index'])->name('relatiebeheer.zoeken');
        Route::get('/relatie-documenten/{document}/download', [App\Http\Controllers\Relatie\RelatieDocumentController::class, 'download'])->name('relatiedocumenten.download');
        Route::get('/overeenkomsten/{overeenkomst}/download', [App\Http\Controllers\Relatie\OvereenkomstController::class, 'download'])->name('overeenkomsten.download');
        Route::get('/organisaties/{organisatie}', [App\Http\Controllers\Relatie\OrganisatieController::class, 'show'])->name('relaties.show');
    });

    /*
    |--------------------------------------------------------------------------
    | Module: HR / Personeelszaken
    |--------------------------------------------------------------------------
    | Rolverdeling:
    |  - HR-medewerker: volledige personeelsadministratie én leidinggevende
    |    (één gecombineerde rol; verlof goedkeuren, alle medewerkers).
    |  - Beheerder: alles. Schoolbestuur: instellingsbrede inzage (alleen-lezen).
    | Muteren staat in de beheergroep; inzage/downloads in de bredere inzagegroep.
    | Beheer-routes bewust vóór de inzage-routes (literal 'nieuw' vóór {medewerker}).
    */
    Route::middleware('rol:hrmedewerker,beheerder')->prefix('hr')->group(function () {
        Route::get('/medewerkers/nieuw', [App\Http\Controllers\Hr\MedewerkerController::class, 'create'])->name('medewerkers.create');
        Route::post('/medewerkers', [App\Http\Controllers\Hr\MedewerkerController::class, 'store'])->name('medewerkers.store');
        Route::get('/medewerkers/{medewerker}/bewerken', [App\Http\Controllers\Hr\MedewerkerController::class, 'edit'])->name('medewerkers.edit');
        Route::put('/medewerkers/{medewerker}', [App\Http\Controllers\Hr\MedewerkerController::class, 'update'])->name('medewerkers.update');
        // Definitief verwijderen (foutieve/dubbele records) — met bevestiging.
        Route::delete('/medewerkers/{medewerker}', [App\Http\Controllers\Hr\MedewerkerController::class, 'destroy'])->name('medewerkers.destroy');

        Route::get('/medewerkers/{medewerker}/dienstverband/nieuw', [App\Http\Controllers\Hr\DienstverbandController::class, 'create'])->name('dienstverbanden.create');
        Route::post('/medewerkers/{medewerker}/dienstverbanden', [App\Http\Controllers\Hr\DienstverbandController::class, 'store'])->name('dienstverbanden.store');
        Route::get('/dienstverbanden/{dienstverband}/bewerken', [App\Http\Controllers\Hr\DienstverbandController::class, 'edit'])->name('dienstverbanden.edit');
        Route::put('/dienstverbanden/{dienstverband}', [App\Http\Controllers\Hr\DienstverbandController::class, 'update'])->name('dienstverbanden.update');
        Route::delete('/dienstverbanden/{dienstverband}', [App\Http\Controllers\Hr\DienstverbandController::class, 'destroy'])->name('dienstverbanden.destroy');

        Route::post('/medewerkers/{medewerker}/documenten', [App\Http\Controllers\Hr\HrDocumentController::class, 'store'])->name('hrdocumenten.store');
        Route::delete('/hr-documenten/{document}', [App\Http\Controllers\Hr\HrDocumentController::class, 'destroy'])->name('hrdocumenten.destroy');
        Route::post('/medewerkers/{medewerker}/verlofsaldo', [App\Http\Controllers\Hr\VerlofsaldoController::class, 'bijwerken'])->name('verlofsaldo.bijwerken');

        // Organisatiestructuur-beheer (Fase F+): afdelingen en functies rechtstreeks
        // beheren door HR (voorheen alleen Beheer via Opzoektabellen).
        Route::post('/organisatie/afdelingen', [App\Http\Controllers\Hr\OrganisatiebeheerController::class, 'afdelingStore'])->name('hr.afdeling.store');
        Route::put('/organisatie/afdelingen/{afdeling}', [App\Http\Controllers\Hr\OrganisatiebeheerController::class, 'afdelingUpdate'])->name('hr.afdeling.update');
        Route::delete('/organisatie/afdelingen/{afdeling}', [App\Http\Controllers\Hr\OrganisatiebeheerController::class, 'afdelingDestroy'])->name('hr.afdeling.destroy');
        Route::post('/organisatie/functies', [App\Http\Controllers\Hr\OrganisatiebeheerController::class, 'functieStore'])->name('hr.functie.store');
        Route::put('/organisatie/functies/{functie}', [App\Http\Controllers\Hr\OrganisatiebeheerController::class, 'functieUpdate'])->name('hr.functie.update');
        Route::delete('/organisatie/functies/{functie}', [App\Http\Controllers\Hr\OrganisatiebeheerController::class, 'functieDestroy'])->name('hr.functie.destroy');
    });

    Route::middleware('rol:hrmedewerker,beheerder,bestuur')->prefix('hr')->group(function () {
        Route::get('/', [App\Http\Controllers\Hr\HrDashboardController::class, 'index'])->name('hr.dashboard');
        Route::get('/medewerkers', [App\Http\Controllers\Hr\MedewerkerController::class, 'index'])->name('medewerkers');
        Route::get('/hr-documenten/{document}/download', [App\Http\Controllers\Hr\HrDocumentController::class, 'download'])->name('hrdocumenten.download');
        // Rapportages & organisatiestructuur (Fase D).
        Route::get('/rapport', [App\Http\Controllers\Hr\HrRapportController::class, 'rapport'])->name('hr.rapport');
        Route::get('/rapport/export.csv', [App\Http\Controllers\Hr\HrRapportController::class, 'export'])->name('hr.rapport.export');
        // Verzuim & verlof per medewerker — elke medewerker volgen op ziekte en verlof.
        Route::get('/verzuim-verlof', [App\Http\Controllers\Hr\HrRapportController::class, 'verzuimVerlof'])->name('hr.verzuimverlof');
        Route::get('/verzuim-verlof/export.csv', [App\Http\Controllers\Hr\HrRapportController::class, 'verzuimVerlofExport'])->name('hr.verzuimverlof.export');
        Route::get('/organisatie', [App\Http\Controllers\Hr\HrRapportController::class, 'organisatie'])->name('hr.organisatie');
        // Slimme functies (Fase G): globaal zoeken + signaleringen (aflopende
        // contracten + verzuim volgens de Wet Verbetering Poortwachter).
        Route::get('/zoeken', [App\Http\Controllers\Hr\ZoekController::class, 'index'])->name('hr.zoeken');
        Route::get('/signaleringen', [App\Http\Controllers\Hr\SignaleringController::class, 'index'])->name('hr.signaleringen');
        Route::get('/medewerkers/{medewerker}', [App\Http\Controllers\Hr\MedewerkerController::class, 'show'])->name('medewerkers.show');
    });

    // Verlof & verzuim (Fase B). Overzicht + beoordelen + ziek-/herstelmelding:
    // de HR-medewerker (tevens leidinggevende) en Beheer.
    Route::middleware('rol:hrmedewerker,beheerder')->prefix('hr')->group(function () {
        Route::get('/verlof', [App\Http\Controllers\Hr\VerlofController::class, 'index'])->name('verlof');
        Route::post('/verlofaanvragen/{aanvraag}/beoordelen', [App\Http\Controllers\Hr\VerlofController::class, 'beoordelen'])->name('verlof.beoordelen');
        Route::get('/verzuim', [App\Http\Controllers\Hr\ZiekmeldingController::class, 'index'])->name('verzuim');
        Route::post('/ziekmeldingen', [App\Http\Controllers\Hr\ZiekmeldingController::class, 'store'])->name('ziekmeldingen.store');
        Route::post('/ziekmeldingen/{ziekmelding}/herstel', [App\Http\Controllers\Hr\ZiekmeldingController::class, 'herstel'])->name('ziekmeldingen.herstel');

        // Gesprekken & performance (Fase C).
        Route::get('/gesprekken', [App\Http\Controllers\Hr\GesprekController::class, 'index'])->name('gesprekken');
        Route::get('/medewerkers/{medewerker}/gesprekken/nieuw', [App\Http\Controllers\Hr\GesprekController::class, 'create'])->name('gesprekken.create');
        Route::post('/medewerkers/{medewerker}/gesprekken', [App\Http\Controllers\Hr\GesprekController::class, 'store'])->name('gesprekken.store');
        Route::get('/gesprekken/{gesprek}', [App\Http\Controllers\Hr\GesprekController::class, 'show'])->name('gesprekken.show');
        Route::put('/gesprekken/{gesprek}', [App\Http\Controllers\Hr\GesprekController::class, 'update'])->name('gesprekken.update');
        Route::delete('/gesprekken/{gesprek}', [App\Http\Controllers\Hr\GesprekController::class, 'destroy'])->name('gesprekken.destroy');
        Route::post('/gesprekken/{gesprek}/doelen', [App\Http\Controllers\Hr\GesprekController::class, 'doelStore'])->name('gesprekken.doel.store');
        Route::delete('/gespreksdoelen/{doel}', [App\Http\Controllers\Hr\GesprekController::class, 'doelDestroy'])->name('gesprekken.doel.destroy');
        Route::post('/gesprekken/{gesprek}/competenties', [App\Http\Controllers\Hr\GesprekController::class, 'competentieStore'])->name('gesprekken.competentie.store');
        Route::delete('/competentiescores/{score}', [App\Http\Controllers\Hr\GesprekController::class, 'competentieDestroy'])->name('gesprekken.competentie.destroy');

        // Interne notities per medewerker (contactmomenten/gespreksverslagen).
        Route::post('/medewerkers/{medewerker}/notities', [App\Http\Controllers\Hr\MedewerkerController::class, 'notitieStore'])->name('medewerkers.notities.store');
        Route::delete('/medewerkers/{medewerker}/notities/{notitie}', [App\Http\Controllers\Hr\MedewerkerController::class, 'notitieDestroy'])->name('medewerkers.notities.destroy');

        // Onboarding/offboarding-checklists (Fase E).
        Route::post('/medewerkers/{medewerker}/checklist/start', [App\Http\Controllers\Hr\ChecklistController::class, 'start'])->name('checklist.start');
        Route::post('/medewerkers/{medewerker}/checklisttaken', [App\Http\Controllers\Hr\ChecklistController::class, 'store'])->name('checklist.store');
        Route::post('/checklisttaken/{taak}/gereed', [App\Http\Controllers\Hr\ChecklistController::class, 'toggle'])->name('checklist.toggle');
        Route::delete('/checklisttaken/{taak}', [App\Http\Controllers\Hr\ChecklistController::class, 'destroy'])->name('checklist.destroy');
    });

    // Self-service "Mijn HR" & verlof: elke ingelogde medewerker (met een gekoppeld
    // dossier). Geen rol-beperking; de controllers vereisen een personeelsrecord.
    Route::prefix('hr')->group(function () {
        // Mijn HR — eigen dossier + iCal-agenda + eigen documenten (Fase F).
        Route::get('/mijn', [App\Http\Controllers\Hr\MijnHrController::class, 'index'])->name('hr.mijn');
        Route::get('/mijn/agenda.ics', [App\Http\Controllers\Hr\MijnHrController::class, 'agenda'])->name('hr.mijn.agenda');
        Route::get('/mijn/documenten/{document}/download', [App\Http\Controllers\Hr\MijnHrController::class, 'document'])->name('hr.mijn.document');

        // Mijn verlof — zelfservice-aanvragen (Fase B).
        Route::get('/mijn/verlof', [App\Http\Controllers\Hr\VerlofController::class, 'mijn'])->name('verlof.mijn');
        Route::get('/mijn/verlof/nieuw', [App\Http\Controllers\Hr\VerlofController::class, 'create'])->name('verlof.create');
        Route::post('/mijn/verlof', [App\Http\Controllers\Hr\VerlofController::class, 'store'])->name('verlof.store');
        Route::post('/verlofaanvragen/{aanvraag}/intrekken', [App\Http\Controllers\Hr\VerlofController::class, 'intrekken'])->name('verlof.intrekken');
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

    // --- Vervroegd afstuderen VRIJGEVEN: uitsluitend de Examencommissie (+ Beheerder).
    // Een academisch besluit bij vrijstellingen/eerder behaalde EC; Studentenzaken
    // voert het afstuderen daarna administratief uit.
    Route::middleware('rol:examencommissie,beheerder')->group(function () {
        Route::post('/inschrijvingen/{inschrijving}/vervroegd-afstuderen', [App\Http\Controllers\VervroegdAfstuderenController::class, 'bijwerken'])->name('inschrijving.vervroegd-afstuderen');
    });

    // --- Afstudeerproces (examencommissie-gedreven, 5 stappen). Kandidatenlijst is
    // inzage voor EC/SZ/Directie/Beheer; starten/afbreken doet de examencommissie;
    // elke stap wordt STRIKT door de verantwoordelijke rol afgevinkt (controller).
    Route::middleware('rol:examencommissie,studentenzaken,directie,beheerder')->group(function () {
        Route::get('/afstuderen/kandidaten', [App\Http\Controllers\AfstudeerprocesController::class, 'kandidaten'])->name('afstuderen.kandidaten');
    });
    Route::middleware('rol:examencommissie,studentenzaken,beheerder')->group(function () {
        Route::post('/afstudeerstappen/{stap}/afvinken', [App\Http\Controllers\AfstudeerprocesController::class, 'stapAfvinken'])->name('afstuderen.stap.afvinken');
    });
    Route::middleware('rol:examencommissie,beheerder')->group(function () {
        Route::post('/inschrijvingen/{inschrijving}/afstudeerproces', [App\Http\Controllers\AfstudeerprocesController::class, 'start'])->name('afstuderen.proces.start');
        Route::post('/afstudeerprocessen/{proces}/afbreken', [App\Http\Controllers\AfstudeerprocesController::class, 'afbreken'])->name('afstuderen.proces.afbreken');
    });

    // --- Historisch studentdossier (gemigreerde cijfers): cijfer-bevoegde rollen
    // + Beheerder (verificatie). NIET voor Studentenzaken — rolscheiding op cijfers.
    Route::middleware('rol:examencommissie,directie,beheerder')->group(function () {
        Route::get('/historisch-dossier', [App\Http\Controllers\HistorischDossierController::class, 'index'])->name('historisch.index');
        Route::get('/historisch-dossier/bulk', [App\Http\Controllers\HistorischDossierController::class, 'bulk'])->name('historisch.bulk');
        Route::get('/historisch-dossier/{student}', [App\Http\Controllers\HistorischDossierController::class, 'show'])->name('historisch.show');
        Route::get('/historisch-dossier/{student}/pdf', [App\Http\Controllers\HistorischDossierController::class, 'pdf'])->name('historisch.pdf');
    });

    // --- Identiteit & inschrijving beheren: SZ, Beheerder ---
    Route::middleware('rol:studentenzaken,beheerder')->group(function () {
        // Vrijstellingsbesluit van de examencommissie verwerken (één klik).
        Route::post('/vrijstellingsbesluiten/{besluit}/verwerken', [App\Http\Controllers\VrijstellingsbesluitController::class, 'verwerk'])->name('vrijstellingsbesluiten.verwerken');

        // TIJDELIJK: migratie uit de oude Access-database (per-jaar CSV's).
        Route::get('/migratie', [App\Http\Controllers\MigratieController::class, 'index'])->name('migratie');
        Route::post('/migratie', [App\Http\Controllers\MigratieController::class, 'verwerk'])->name('migratie.verwerk');

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

        // Afstuderen (terminale eindstatus → alumnus; alleen vanuit het laatste leerjaar)
        Route::get('/studenten/{student}/afstuderen', [InschrijvingActiesController::class, 'afstuderenForm'])->name('afstuderen.form');
        Route::post('/studenten/{student}/afstuderen', [InschrijvingActiesController::class, 'afstuderen'])->name('afstuderen.store');

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
        Route::post('/gebruikers', [GebruikerController::class, 'store'])->name('gebruikers.store');
        Route::put('/gebruikers/{gebruiker}/rol', [GebruikerController::class, 'updateRol'])->name('gebruikers.rol');
        // Opleidingtoewijzing voor directieleden (zichtbaarheid per opleiding).
        Route::put('/gebruikers/{gebruiker}/opleidingen', [GebruikerController::class, 'updateOpleidingen'])->name('gebruikers.opleidingen');

        Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log');

        // Onderwijsnieuws — bronnenbeheer + handmatig ophalen/toevoegen.
        Route::get('/beheer/nieuws', [App\Http\Controllers\NieuwsController::class, 'index'])->name('nieuws');
        Route::post('/beheer/nieuws/ophalen', [App\Http\Controllers\NieuwsController::class, 'ophalen'])->name('nieuws.ophalen');
        Route::put('/beheer/nieuws/bron/{bron}/toggle', [App\Http\Controllers\NieuwsController::class, 'bronToggle'])->name('nieuws.bron.toggle');
        Route::post('/beheer/nieuws/bericht', [App\Http\Controllers\NieuwsController::class, 'berichtToevoegen'])->name('nieuws.bericht');
        Route::delete('/beheer/nieuws/bericht/{bericht}', [App\Http\Controllers\NieuwsController::class, 'berichtVerwijderen'])->name('nieuws.bericht.verwijderen');

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
