<!DOCTYPE html>
<html lang="nl">
@php
  $logoPad = public_path('assets/img/iuasr-logo.png');
  $logo = is_file($logoPad) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPad)) : null;
@endphp
<head>
  <meta charset="utf-8">
  <style>
    @page { margin: 42px 46px 64px 46px; }
    body { font-family: "DejaVu Sans", sans-serif; color: #1E1446; font-size: 10.5pt; line-height: 1.5; }
    #footer { position: fixed; bottom: -44px; left: 0; right: 0; height: 30px; border-top: 1px solid #ddd; padding-top: 6px; font-size: 8pt; color: #666; }
    #footer .r { text-align: right; }
    #footer .num:after { content: counter(page); }
    .cover { border-bottom: 3px solid #1E1446; padding-bottom: 14px; margin-bottom: 20px; }
    .cover img { height: 96px; }
    .cover h1 { font-size: 23pt; font-weight: bold; margin: 14px 0 2px; }
    .cover .sub { font-size: 11pt; color: #666; margin: 0; }
    h2 { font-size: 14pt; color: #C8102E; margin: 20px 0 6px; border-bottom: 1px solid #eee; padding-bottom: 3px; page-break-after: avoid; }
    h3 { font-size: 11.5pt; margin: 14px 0 4px; page-break-after: avoid; }
    p { margin: 0 0 9px; }
    ul, ol { margin: 0 0 10px; padding-left: 20px; }
    li { margin: 0 0 5px; }
    code, .cmd { font-family: "DejaVu Sans Mono", monospace; font-size: 9pt; }
    .cmd { display: block; background: #1E1446; color: #EDEBF5; padding: 8px 12px; border-radius: 5px; margin: 6px 0 12px; white-space: pre-wrap; word-break: break-all; }
    .kv { width: 100%; border-collapse: collapse; font-size: 9.5pt; margin: 6px 0 12px; }
    .kv td { border-bottom: 1px solid #eee; padding: 5px 8px; }
    .kv td.k { color: #666; width: 190px; }
    .let { background: #FBEFEF; border-left: 3px solid #C8102E; padding: 8px 12px; margin: 10px 0; font-size: 10pt; }
    .tip { background: #F0F5F3; border-left: 3px solid #285C4D; padding: 8px 12px; margin: 10px 0; font-size: 10pt; }
    b { color: #1E1446; }
    ol.stap > li { margin-bottom: 9px; }
  </style>
</head>
<body>
  <div id="footer">
    <table style="width:100%;"><tr>
      <td>IUASR SIS — Technische handleiding &amp; herstel · VERTROUWELIJK · {{ now()->format('d-m-Y') }}</td>
      <td class="r">Pagina <span class="num"></span></td>
    </tr></table>
  </div>

  <div class="cover">
    @if ($logo)<img src="{{ $logo }}" alt="IUASR">@endif
    <h1>Technische handleiding &amp; data-recovery</h1>
    <p class="sub">Voor technisch beheer · Intern Studentbeheersysteem (SIS) · IUASR</p>
  </div>

  <div class="let">Dit document is bestemd voor <b>technisch personeel/beheerders</b>. Het beschrijft de architectuur, het maken van back-ups en de <b>herstelprocedure</b>. Bewaar het vertrouwelijk.</div>

  <h2>0. Platform: modules</h2>
  <p>Het systeem is een multi-module platform. Na de login (route <code>modules.kiezen</code>, <code>/modules</code>) kiest de gebruiker een module. Tabel <code>modules</code> (<code>sleutel</code>, <code>naam</code>, <code>actief</code>, <code>volgorde</code>) is de registry; de vijf modules worden in de create-migratie ingevoegd. Nieuwe module toevoegen = één rij plus haar schermen, geen ingreep in de kern.</p>
  <p>Moduletoegang volgt uit de rol: <code>Rol::moduleSleutels()</code> geeft de toegestane modulesleutels (<code>['*']</code> voor Beheerder). <code>Module::toegankelijkVoor()</code> toetst toegang, <code>bruikbaarVoor()</code> = toegankelijk én <code>actief</code>. Een module opent op <code>Module::startRoute()</code> (nu alleen Studentenzaken → <code>dashboard</code>). Nog niet gebouwde modules staan op <code>actief=false</code> en worden als 'Binnenkort' getoond. De rollen blijven de bestaande enum <code>App\Enums\Rol</code>; de cursus-specifieke rollen komen bij de Cursussen-module.</p>

  <h2>0b. Module Cursussen Administratie</h2>
  <p>Aparte administratie, lichter regime (geen BSN/DUO). Tabellen: <code>cursussen</code> (code, naam, cursusgeld, start/eind, directeur_id, actief; drie cursussen in de create-migratie), <code>cursisten</code> (aangepaste, lichtere kopie van <code>studenten</code>; <code>cursistnummer</code> = C + jaar + volgnr via <code>CursistnummerGenerator</code>), <code>cursusinschrijvingen</code> (cursist × cursus, <code>totaalbedrag</code> als momentopname van het cursusgeld). Controllers onder <code>App\Http\Controllers\Cursus\*</code>, routes onder prefix <code>cursussen</code>. Rol <code>Cursusadministratie</code> toegevoegd aan de enum (enum-kolom via ALTER). Bulk-import leest .xlsx én .csv via <code>App\Support\Tabellezer</code> (PhpSpreadsheet), kolomherkenning op naam. Cursusdirecteuren met toegangsbeperking volgen in een latere fase.</p>
  <p><b>Cursus kopiëren (Beheerder).</b> <code>CursusController@kopieForm</code> op route <code>cursussen.kopieren</code> (<code>/cursussen/beheer/{bron}/kopieren</code>, <code>rol:beheerder</code>) rendert het bestaande <code>cursussen.form</code> vooraf ingevuld met een niet-persisted <code>new Cursus()</code> op basis van de bron (cursusgeld, omschrijving, looptijd, directeur; code leeg). Opslaan verloopt via de reguliere <code>store</code> — er is dus geen aparte kopie-actie; de nieuwe cursus ontstaat uit de ingevulde velden. Inschrijvingen/cursisten worden NIET meegekopieerd. Let op de route-model-binding: de parameter heet bewust <code>{bron}</code> zodat hij bindt op <code>kopieForm(Cursus $bron)</code> (naam moet overeenkomen, anders krijgt de methode een leeg model).</p>
  <p><b>Directe cursusknoppen op het welkomstscherm.</b> <code>ModuleController@index</code> geeft de voor de gebruiker zichtbare, actieve cursussen mee (<code>Cursus::query()->zichtbaarVoor()</code>); <code>modules/index.blade.php</code> rendert ze als aparte tegels onder "Cursussen". Elke tegel linkt naar de cursus-startpagina <code>cursussen.cursus</code> (<code>/cursussen/cursus/{cursus}</code>, <code>CursusDashboardController@cursus</code>, view <code>cursussen.cursus</code>) in de dashboardgroep <code>rol:cursusadministratie,financien,beheerder,bestuur</code> met <code>abort_unless($cursus->zichtbaarVoor())</code>. De pagina toont per cursus de statusverdeling, financiële kerncijfers (<code>Cursusrapport::financieelTotaal</code>) en de cursisten, met snelkoppelingen naar cursusgelden (<code>?cursus=</code>) en rapportage. De naam-tegel toont bewust géén cursusgeld.</p>
  <p><b>Cursusrapportage &amp; dashboards (Fase E).</b> Aggregatieklasse <code>App\Support\Cursusrapport</code>: per cursus de inschrijvingen (uitgesplitst per <code>CursusinschrijvingStatus</code>) en de cursusgelden (verschuldigd/betaald/openstaand + betaalgraad, via <code>Cursusgeldstatus</code>), plus totalen en een verdeling naar betaalmethode. Financiële cijfers tellen alleen niet-geannuleerde inschrijvingen; alleen betalingen met status <code>Betaald</code> gelden als voldaan. <code>Cursus\CursusrapportController</code> (<code>index</code> = view <code>cursussen.rapport</code>, <code>export</code> = CSV op cursistniveau, gelogd als <code>INZAGE</code> veld <code>cursusrapport_export</code>). Routes <code>cursussen.rapport</code> + <code>cursussen.rapport.export</code> in de dashboardgroep <code>rol:cursusadministratie,financien,beheerder,bestuur</code>, dezelfde <code>Cursus::query()->zichtbaarVoor()</code>-scoping als het dashboard (directeur = eigen cursus[sen]). Het cursusdashboard toont via <code>Cursusrapport::financieelTotaal()</code> de betaalgraad en het openstaande bedrag; de Bestuurspagina linkt door naar het rapport. Rendering met de bestaande chart-partials. 8 tests (<code>CursusrapportTest</code>); 327 groen.</p>
  <p><b>Cursusdirecteuren — toegang per cursus (Fase D).</b> De rol <code>Cursusadministratie</code> is voortaan <b>cursusgebonden</b> (need-to-know), analoog aan de opleidinggebonden Directie. Sleutel is de bestaande FK <code>cursussen.directeur_id</code> (geen pivot nodig): een directeur dirigeert de cursussen waarvan hij <code>directeur_id</code> is (<code>User::gedirigeerdeCursussen()</code>, <code>User::cursusIds()</code>, <code>User::isCursusBeperkt()</code> → alleen Cursusadministratie). Scopes <code>Cursus::scopeZichtbaarVoor()</code> / <code>Cursist::scopeZichtbaarVoor()</code> (aanroepen via <code>::query()->zichtbaarVoor()</code> om botsing met de gelijknamige instance-guard te vermijden) + instance-guards <code>Cursus::zichtbaarVoor()/beheerbaarVoor()</code> en <code>Cursist::zichtbaarVoor()</code>. Toegepast in alle vier de admin-controllers: dashboard (drie tellingen gescoped), cursusbeheerlijst, cursistenlijst + withCount, cursus-dropdowns, en per-record guards op edit/update/show/inschrijven. <b>Rolverdeling in de routes:</b> cursus aanmaken/verwijderen én directeur toewijzen is <code>rol:beheerder</code> (server-side; in <code>CursusController::update</code> wordt <code>directeur_id</code> alleen toegepast als de gebruiker Beheerder is → geen rechtenescalatie). Cursusbeheer-details en cursisten/inschrijvingen muteren: <code>rol:cursusadministratie,beheerder</code> (gescoped). Boekhouding (betalingen): <code>rol:financien,beheerder</code>, ongescoped (alle cursussen). Dashboard en cursisteninzage ook voor het <b>Schoolbestuur</b> (<code>Rol::moduleSleutels()</code> voor Bestuur = <code>['studentenzaken','cursussen']</code>; <code>Rol::magCursusInzien()</code> = Cursusadministratie/Beheerder/Bestuur), alleen-lezen — de mutatieknoppen hangen in de views aan <code>magCursusBeheer()</code>. Seed (definitief, opdrachtgever): Arabische Taal → Hafsa; Hifz + Ijaaza → Omar (twee directeuren — Arabisch apart, Hifz en Ijaaza samen). Guarded datamigraties (<code>wijs_cursusdirecteuren_toe</code>, <code>herverdeel_cursusdirecteuren_een_per_cursus</code>) vullen/herverdelen een bestaande database (draaien op een verse migratie vóór de seeders en doen dan niets). 15 tests (<code>CursusdirecteurTest</code> + tegentest); 312 groen.</p>
  <p><b>Cursusgelden &amp; boekhouding (Fase C).</b> Tabel <code>cursusbetalingen</code> (<code>cursusinschrijving_id</code> FK cascade, <code>betaalmethode</code>, <code>bedrag</code> decimal(10,2), <code>betaaldatum</code>, <code>betalingsstatus</code>, <code>referentienummer</code>, <code>opmerking</code>, <code>geregistreerd_door_id</code> FK users nullOnDelete). Enums <code>App\Enums\Betaalmethode</code> (ideal/overboeking/contant) en <code>App\Enums\Cursusbetaalstatus</code> (in_afwachting/betaald/mislukt/terugbetaald; alleen <code>Betaald</code> telt als voldaan). De financiële status per inschrijving is <b>afgeleid, niet opgeslagen</b>: <code>App\Support\Cursusgeldstatus::voor()</code> zet <code>totaalbedrag</code> af tegen de som van de betalingen met status <code>Betaald</code> → <code>{totaal, betaald, openstaand, status}</code> (voldaan/deels/open). Controller <code>Cursus\CursusbetalingController</code> (overzicht/registreer/bijwerken/verwijderen); routes onder prefix <code>cursussen</code> met middleware <code>rol:financien,beheerder</code>. Het cursusdashboard staat op <code>rol:cursusadministratie,financien,beheerder</code> (de modulekiezer stuurt Financiën naar <code>cursussen.dashboard</code>); cursus-/cursistbeheer blijft <code>rol:cursusadministratie,beheerder</code>. Rolregels: <code>Rol::magCursusBeheer()</code> en <code>Rol::magCursusFinancien()</code> (met <code>User</code>-delegates) sturen de rolbewuste sidebar en dashboardknoppen. Wijzigen/verwijderen van betalingen wordt via <code>AuditLogger</code> gelogd (veld <code>cursusbetaling</code>).</p>

  <h2>0b2. Module Relatiebeheer &amp; Stagebeheer (Fase A)</h2>
  <p><b>Opzet.</b> Opleidingoverstijgende module (PABO, Bachelor Islamitische Theologie, Master IGV) voor externe relaties. De placeholder-module <code>stage</code> is via de datamigratie <code>stage_module_naar_relatiebeheer</code> hernoemd naar sleutel <code>relatiebeheer</code> (naam "Relatiebeheer &amp; Stage") en met <code>activeer_relatiebeheer_module</code> op <code>actief=true</code> gezet; <code>Module::START_ROUTES['relatiebeheer'] = 'relaties'</code>. Twee nieuwe rollen <code>Relatiebeheerder</code> en <code>Stagecoordinator</code> in <code>App\Enums\Rol</code> (enum-kolom via ALTER-migratie <code>add_relatiebeheer_rollen</code>, die <code>Rol::waarden()</code> leest — enum-case dus vóór de migratie). Ontwerp: <code>docs/MODULE-RELATIEBEHEER.md</code>.</p>
  <p><b>Datamodel.</b> <code>organisatie_types</code> (opzoektabel; <code>code</code> uniek, <code>naam</code>, <code>opleiding_id</code> nullable = per opleiding instelbaar of null=alle, <code>actief</code>), <code>organisaties</code> (<code>relatienummer</code> uniek/leesbaar via <code>App\Support\RelatienummerGenerator</code> = <code>R</code> + jaar + volgnr, <code>organisatie_type_id</code> nullOnDelete, adres/contactvelden, <code>actief</code>, <code>opmerkingen</code>) en de pivot <code>organisatie_opleidingen</code> (echte FK's, cascade, unique). Modellen <code>App\Models\Organisatie</code> en <code>OrganisatieType</code>.</p>
  <p><b>Scoping &amp; rollen.</b> Opleidinggebonden (need-to-know), analoog aan de Directie: de koppeling loopt via dezelfde <code>User::opleidingen()</code>-relatie (<code>directie_opleidingen</code>). <code>Rol::isRelatieBeperkt()</code> = Relatiebeheerder/Stagecoordinator/Directie; <code>Organisatie::scopeZichtbaarVoor()</code> filtert op <code>whereHas('opleidingen', ... whereIn opleidingIds)</code>, plus instance-guards <code>zichtbaarVoor()</code>/<code>beheerbaarVoor()</code>. Rolregels: <code>Rol::magRelatiebeheer()</code> (Relatiebeheerder/Stagecoordinator/Beheerder = aanmaken/wijzigen), <code>magStagebeheer()</code> (Stagecoordinator/Beheerder — stageplaatsen, latere fase), <code>magRelatieInzien()</code> (+ Directie, Bestuur). <code>moduleSleutels()</code>: de twee nieuwe rollen → <code>['relatiebeheer']</code>, Directie → <code>['studentenzaken','relatiebeheer']</code>, Bestuur → <code>['studentenzaken','cursussen','relatiebeheer']</code>. <b>Let op:</b> bij een nieuwe rol alle exhaustive <code>match</code>-methodes zonder <code>default</code> in <code>Rol</code> aanvullen (label, magCijfersInzien, magInschrijvingBeheren, magPresentieInzien, magAanwezigheidsregelingZien, moduleSleutels).</p>
  <p><b>Controller/routes/views.</b> <code>App\Http\Controllers\Relatie\OrganisatieController</code> (index met filters q/type/opleiding/status, create/store/show/edit/update/status-toggle). Routes onder prefix <code>relatiebeheer</code>: beheergroep <code>rol:relatiebeheerder,stagecoordinator,beheerder</code> (bewust vóór de inzagegroep zodat <code>/organisaties/nieuw</code> niet als id bindt), inzagegroep <code>rol:relatiebeheerder,stagecoordinator,directie,bestuur,beheerder</code> (<code>relaties</code>, <code>relaties.show</code>). Views <code>resources/views/relaties/{index,form,show}</code>; sidebar-tak <code>$inRelatiemodule = routeIs('relaties*')</code>. Aanmaken/wijzigen gelogd via <code>AuditLogger</code> (veld <code>organisatie</code>/<code>organisatie_status</code>). Server-side afgedwongen dat een opleidinggebonden gebruiker alleen de <b>eigen</b> opleiding(en) kan koppelen (<code>Rule::in($toegestaan)</code> op <code>opleidingen.*</code>). <b>AVG-grens:</b> uitsluitend organisatie- en contactgegevens, nooit leerling-/cliëntgegevens. Landing-redirect (<code>DashboardController</code>) stuurt module-only rollen naar <code>relaties</code>. Startdata op de draaiende DB via guarded <code>seed_relatiebeheer_startdata</code> (no-op op verse test-DB). 10 tests (<code>RelatiebeheerModuleTest</code>); 361 groen.</p>

  <p><b>Contactpersonen &amp; relatiekaart (Fase B).</b> Tabel <code>contactpersonen</code> (<code>organisatie_id</code> FK cascade, voornaam/achternaam, functie, email, mobiel, telefoon, afdeling, <code>voorkeur_communicatie</code> [e-mail/telefoon/teams], linkedin, <code>actief</code>). Model <code>App\Models\Contactpersoon</code> (<code>belongsTo</code> organisatie, <code>volledigeNaam()</code>, scope <code>actief</code>); <code>Organisatie::contactpersonen()</code> <code>HasMany</code>. <code>Relatie\ContactpersoonController</code> (create/store genest onder <code>{organisatie}</code>, edit/update/status op <code>{contactpersoon}</code>) in de beheergroep <code>rol:relatiebeheerder,stagecoordinator,beheerder</code>; autorisatie volgt de organisatie (<code>abort_unless($cp->organisatie->beheerbaarVoor())</code>). De relatiekaart (<code>relaties.show</code>) laadt de contactpersonen (eager, actief eerst) en toont ze in een paneel met toevoegen/bewerken/inactiveren; view <code>relaties/contactpersoon-form</code>. Sidebar-tak <code>$inRelatiemodule</code> matcht ook <code>contactpersonen*</code>. Mutaties gelogd (<code>veld: contactpersoon</code>/<code>contactpersoon_status</code>); inactiveren i.p.v. verwijderen (historie/AVG). Synthetische seed in <code>OrganisatieSeeder</code> + guarded datamigratie <code>seed_contactpersonen_startdata</code>. 5 tests (<code>ContactpersoonTest</code>); 366 groen.</p>

  <p><b>Contactmomenten, notities &amp; tijdlijn (Fase C).</b> Tabellen <code>contactmoment_types</code> (opzoektabel, in <code>ReferentieController</code> als <code>contactmomenttypes</code>), <code>contactmomenten</code> (FK organisatie cascade; contactpersoon/type/medewerker nullOnDelete; datum, tijd, onderwerp, samenvatting, <code>vervolgdatum</code>) en <code>relatie_notities</code> (organisatie cascade, auteur nullOnDelete, categorie, tags, tekst). Modellen <code>Contactmoment</code>, <code>ContactmomentType</code>, <code>RelatieNotitie</code>; <code>Organisatie::contactmomenten()/notities()</code>. Controllers <code>Relatie\ContactmomentController</code> (create/store/edit/update — geen delete, historie) en <code>Relatie\RelatieNotitieController</code> (store/destroy), beide in de beheergroep <code>rol:relatiebeheerder,stagecoordinator,beheerder</code>, autorisatie via <code>organisatie->beheerbaarVoor()</code>. Bij store wordt de <code>contactpersoon_id</code> gevalideerd met <code>Rule::in</code> op de contactpersonen van díe organisatie (geen kruiskoppeling). Contactmomenten worden gelogd (<code>veld: contactmoment</code>); notities niet (werkinformatie). De <b>tijdlijn</b> is afgeleid via <code>App\Support\Relatietijdlijn::voor()</code> — merge van contactmomenten + notities + audit-logregels (<code>onderwerp_type</code> Organisatie/Contactpersoon), gesorteerd op moment; geen aparte historietabel. De relatiekaart (<code>relaties.show</code>) toont de panelen Contactmomenten, Notities (inline formulier) en Historie/tijdlijn. Views <code>relaties/contactmoment-form</code>. Sidebar-tak matcht ook <code>contactmomenten*</code>. Synthetische seed in <code>OrganisatieSeeder</code> + guarded datamigratie <code>seed_relatie_fase_c_startdata</code>. 6 tests (<code>ContactmomentTest</code>); 372 groen.</p>

  <p><b>Stagebeheer — stageplaatsen &amp; stages (Fase D).</b> Enum <code>App\Enums\Stagestatus</code> (aangevraagd/lopend/afgerond/afgebroken; <code>teltVoorBezetting()</code>, <code>badge()</code>). Tabellen <code>stageplaatsen</code> (organisatie/opleiding/periode FK; leerjaar, aantal_plaatsen, max_studenten, eisen/specialisaties/werkdagen, actief) en <code>stages</code> (leesbaar <code>stagenummer</code> via <code>StagenummerGenerator</code>; student/organisatie/stageplaats/opleiding FK; <code>stagebegeleider_id</code> → users [rol Docent], <code>werkplekbegeleider_id</code> → contactpersonen; start/eind, <code>status</code>, <code>beoordeling</code> voldoende/onvoldoende, toelichting). Modellen <code>Stage</code> (scopeZichtbaarVoor op <code>opleiding_id</code>, beheerbaarVoor = <code>magStagebeheer</code> + zichtbaar) en <code>Stageplaats</code> (<code>bezetting()</code>/<code>vrijePlaatsen()</code> afgeleid uit de stages met status aangevraagd/lopend); <code>Organisatie::stageplaatsen()/stages()</code> + <code>stagesBeheerbaarVoor()</code>. Controllers <code>Relatie\StageplaatsController</code> en <code>Relatie\StageController</code>; muteren in de groep <code>rol:stagecoordinator,beheerder</code>, het Stages-overzicht (<code>stages</code>) in de bredere inzagegroep. <b>Server-side afgedwongen:</b> de opleiding moet een van de organisatie zijn (en binnen de scope van de gebruiker); de student moet een actieve inschrijving in die opleiding hebben; stageplaats en werkplekbegeleider moeten bij dezelfde organisatie horen (alles via <code>Rule::in</code>). De stagebegeleider moet rol Docent hebben. De <b>beoordeling</b> is gevoelig: een wijziging wordt gelogd met veld <code>stage_beoordeling</code> (anders <code>stage</code>). Views <code>relaties/stage-form</code>, <code>relaties/stageplaats-form</code>, <code>relaties/stages-index</code>; panelen Stageplaatsen &amp; Stages op de relatiekaart. Sidebar-item <b>Stages</b>; <code>$inRelatiemodule</code> matcht ook <code>stages*</code>/<code>stageplaatsen*</code>. Synthetische seed (3 stageplaatsen + 1 demo-stage) + guarded datamigratie <code>seed_relatie_fase_d_startdata</code>. 7 tests (<code>StageTest</code>); 379 groen.</p>

  <h2>0c. Bestuurspagina &amp; systeembeheer-snelkoppelingen</h2>
  <p><b>Globale bestuurspagina.</b> Route <code>/bestuur</code> (<code>bestuur</code>, middleware <code>rol:bestuur,beheerder</code>), <code>BestuurController@index</code> → view <code>bestuur.index</code> (in de app-shell). Instellingsbreed, alleen-lezen; hergebruikt de bestaande <code>App\Support\Statistiek</code>-aggregaties (kern, perOpleiding, statusVerdeling, instroom, slaagpercentage, presentie(+Verdeling/PerOpleiding), financieel) plus cursuscijfers (<code>Cursus</code>/<code>Cursist</code>/<code>Cursusinschrijving</code>). Rendering met de chart-partials <code>partials.charts.bar|spark|donut</code> en de dashboardkaarten (<code>sis-chartgrid</code>/<code>sis-chart-card</code>, <code>iuasr-dash-stat</code>). Bereikbaar via een tegel op de modulekiezer en een menu-item in de sidebar (Bestuur + Beheerder).</p>
  <p><b>Systeembeheer op de modulekiezer.</b> <code>resources/views/modules/index.blade.php</code> toont voor <code>rol=beheerder</code> een blok met directe links naar <code>gebruikers</code>, <code>opzoektabellen</code>, <code>audit-log</code>, <code>backup</code> en <code>handleiding.technisch</code> (voorheen alleen via de Studentenzaken-sidebar). De routes zelf blijven in de <code>rol:beheerder</code>-groep; dit zijn uitsluitend snelkoppelingen op de hoofdpagina.</p>

  <h2>1. Architectuur &amp; stack</h2>
  <ul>
    <li><b>Applicatie:</b> PHP + Laravel (server-gerenderd; geen kale PHP/WordPress).</li>
    <li><b>Database:</b> MySQL/MariaDB (InnoDB, echte foreign keys, surrogaatsleutels).</li>
    <li><b>Authenticatie:</b> Microsoft Entra ID (SSO/OIDC) — nooit een eigen login bouwen. In de ontwikkelomgeving een tijdelijke dev-login.</li>
    <li><b>Netwerk:</b> draait intern (intranet), IP-beperkt, gescheiden van het publieke aanmeldportaal.</li>
    <li><b>Gevoelige data:</b> BSN en rekeningnummer versleuteld (Laravel-encryptie met <b>APP_KEY</b>); inzage/mutatie gelogd (audit-log).</li>
  </ul>

  <h2>2. Omgeving (referentie)</h2>
  <table class="kv">
    <tr><td class="k">PHP</td><td><code>~/php/8.3/php.exe</code></td></tr>
    <tr><td class="k">Composer</td><td><code>~/bin/composer.phar</code></td></tr>
    <tr><td class="k">Database-server</td><td>MariaDB (portable) — <code>~/mariadb/mariadb-11.4.9-winx64/bin/</code></td></tr>
    <tr><td class="k">DB-host / poort</td><td><code>127.0.0.1</code> : <code>3307</code></td></tr>
    <tr><td class="k">Database / gebruiker</td><td><code>iuasr_sis</code> / <code>iuasr_sis</code></td></tr>
    <tr><td class="k">Configuratie</td><td><code>.env</code> (in de projectmap) — bevat DB-gegevens en <b>APP_KEY</b></td></tr>
  </table>
  <div class="let"><b>APP_KEY is kritiek.</b> Zonder de originele APP_KEY zijn de versleutelde velden (BSN, rekeningnummer) onherstelbaar. De sleutel zit in <code>.env</code> en wordt meegenomen in de back-up.</div>

  <h3>E-mail (resultaten mailen)</h3>
  <p>De examencommissie kan definitieve resultaten per e-mail naar studenten sturen. In <b>ontwikkeling</b> staat <code>MAIL_MAILER=log</code>: e-mails worden naar <code>storage/logs/laravel.log</code> geschreven, er gaan GEEN echte e-mails uit (AVG, synthetische data). In <b>productie</b> configureert u de IUASR-mailserver in <code>.env</code>:</p>
  <span class="cmd">MAIL_MAILER=smtp
MAIL_HOST=&lt;smtp.iuasr.nl&gt;
MAIL_PORT=587
MAIL_USERNAME=&lt;gebruiker&gt;
MAIL_PASSWORD=&lt;wachtwoord&gt;
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@iuasr.nl
MAIL_FROM_NAME="IUASR Studentenzaken"</span>
  <p>Elke student ontvangt individueel de eigen (ondertekende) cijferlijst als bijlage; verzending wordt gelogd. Overweeg voor grote aantallen een queue (<code>QUEUE_CONNECTION</code> + worker).</p>

  <h2>3. Back-up maken</h2>
  <p>Een volledige back-up wordt gemaakt via de webapplicatie:</p>
  <ol>
    <li>Log in als <b>Beheerder</b>.</li>
    <li>Ga naar <b>Beheer → Back-up &amp; herstel</b>.</li>
    <li>Geef een sterk <b>wachtwoord</b> op (minimaal 8 tekens) en bevestig.</li>
    <li>Klik op <b>Back-up genereren &amp; downloaden</b>. U ontvangt een met AES-256 versleutelde ZIP.</li>
  </ol>
  <p>De ZIP bevat: <code>database.sql</code> (volledige dump), de applicatiebroncode en webpagina's, <code>.env</code> (incl. APP_KEY) en de geüploade bestanden (<code>storage/app</code>). Niet inbegrepen: <code>vendor/</code>, <code>.git/</code> en de referentiemap <code>IUASR/</code>. Het wachtwoord wordt <b>nergens opgeslagen</b>; downloaden wordt ge-audit-logd.</p>
  <div class="tip">Advies: bewaar back-ups versleuteld op een beveiligde, interne locatie en hanteer een bewaarschema (bijv. wekelijks + vóór elke update). Overweeg een periodieke, geautomatiseerde back-up naar een netwerkschijf.</div>

  <h2>4. Herstelprocedure (recovery)</h2>
  <p>Terugzetten is een bewuste beheerhandeling en gebeurt <b>niet</b> vanuit de draaiende applicatie (die kan zichzelf niet veilig overschrijven en een database-restore wist bestaande data). Voer de stappen uit op de server.</p>

  <h3>Stap 1 — Archief uitpakken</h3>
  <p>De Windows Verkenner kan een AES-versleutelde ZIP <b>niet</b> openen. Gebruik het meegeleverde commando (of 7-Zip/WinRAR):</p>
  <span class="cmd">~/php/8.3/php.exe artisan backup:uitpakken "pad/naar/iuasr-sis-backup-JJJJMMDD-UUMM.zip" --doel="pad/naar/hersteld"</span>
  <p>U wordt om het wachtwoord gevraagd (of geef <code>--wachtwoord=...</code>). Het commando verifieert het wachtwoord en pakt uit naar de doelmap.</p>

  <h3>Stap 2 — Bestanden plaatsen</h3>
  <p>Plaats de uitgepakte bestanden in de webroot/projectmap van de (interne) server. Zet ook <code>storage/app</code> (geüploade documenten) terug.</p>

  <h3>Stap 3 — Afhankelijkheden herstellen</h3>
  <span class="cmd">~/bin/composer.phar install --no-dev --optimize-autoloader</span>

  <h3>Stap 4 — Database terugzetten</h3>
  <p>Maak (indien nodig) een lege database en importeer de dump. De dump bevat zowel het schema als de data (DROP/CREATE/INSERT):</p>
  <span class="cmd">~/mariadb/mariadb-11.4.9-winx64/bin/mariadb.exe -h 127.0.0.1 -P 3307 -u iuasr_sis -p iuasr_sis &lt; database.sql</span>
  <div class="let">Let op: dit <b>overschrijft</b> de bestaande gegevens in de database <code>iuasr_sis</code>. Maak eerst een verse back-up van de huidige stand als die nog waarde heeft.</div>

  <h3>Stap 5 — Configuratie controleren</h3>
  <ul>
    <li>Controleer <code>.env</code>: <code>DB_*</code>-gegevens en <code>APP_URL</code>.</li>
    <li><b>Laat <code>APP_KEY</code> ongewijzigd</b> (gelijk aan de back-up), anders zijn BSN/rekeningnummer niet te ontsleutelen.</li>
  </ul>

  <h3>Stap 6 — Cache legen &amp; controleren</h3>
  <span class="cmd">~/php/8.3/php.exe artisan optimize:clear</span>
  <p>Een <code>php artisan migrate</code> is <b>niet</b> nodig: de dump bevat het volledige schema. Controleer tot slot of de applicatie start en of inloggen werkt.</p>

  <h2>5. Losse database-restore (zonder volledige recovery)</h2>
  <p>Alleen de gegevens terugzetten (code ongewijzigd)? Pak het archief uit (stap 1) en voer alleen stap 4 uit met <code>database.sql</code>.</p>

  <h2>6. AVG &amp; beveiliging</h2>
  <ul>
    <li>Back-ups bevatten alle persoonsgegevens én de encryptiesleutel — uitsluitend versleuteld en intern bewaren; niet e-mailen of naar buiten brengen.</li>
    <li>Echte productiedata alleen in de laatste fase, onder toezicht van de Functionaris Gegevensbescherming.</li>
    <li>Inzage/mutatie van cijfers en BSN wordt gelogd (audit-log, alleen-lezen voor Beheer).</li>
    <li>Rolscheiding wordt server-side afgedwongen; wijzig dit niet zonder reden.</li>
    <li><b>Directie is opleidinggebonden.</b> De koppeltabel <code>directie_opleidingen</code> (user &harr; opleiding) bepaalt welke studenten, cijfers en rapporten een directielid ziet. Beheer wijst dit toe via <b>Gebruikers &amp; rollen &rarr; Directie — opleidingtoewijzing</b>. Zonder toewijzing ziet een directielid <b>niets</b> (need-to-know). Een dubbel ingeschreven student is zichtbaar voor de directie van elke opleiding waarin hij/zij actief is. De filtering loopt via <code>User::opleidingIds()</code>, <code>Student::scopeZichtbaarVoor()</code> en per-opleiding gefilterde statistieken.</li>
    <li><b>Presentiegegevens zijn onderwijsinhoudelijk.</b> Studentenzaken, Financiële Administratie en Beheer hebben géén toegang tot presentielijsten of aanwezigheidspercentages (Gate <code>presentie-inzien</code>). Registreren mag alleen de docent van het eigen vak (Gate <code>presentie-registreren</code>). Inzage en mutatie worden gelogd.</li>
  </ul>

  <h2>7. Presentie (aanwezigheidsregistratie)</h2>
  <p>De docent registreert per college de aanwezigheid; dit is verplicht. Het model is bewust <b>genormaliseerd</b>: één regel per student &times; vak &times; onderwijsweek — nooit vaste weekkolommen op de inschrijving.</p>
  <table class="kv">
    <tr><td class="k">Tabel</td><td><code>presenties</code> (<code>inschrijving_id</code>, <code>vak_id</code>, <code>week</code>, <code>aanwezig</code>, <code>geregistreerd_door_id</code>) met unieke sleutel op (<code>inschrijving_id</code>, <code>vak_id</code>, <code>week</code>).</td></tr>
    <tr><td class="k">Regeling</td><td>Kolom <code>inschrijvingen.aanwezigheidsregeling_50</code> (boolean). Bewust op de <b>inschrijving</b>, niet op de student: zij geldt per opleiding en per studiejaar en moet bij herinschrijving opnieuw worden toegekend.</td></tr>
    <tr><td class="k">Normen</td><td><code>config/sis.php</code> &rarr; <code>presentie.weken_per_blok</code> (8), <code>presentie.norm</code> (0.80) en <code>presentie.norm_regeling</code> (0.50).</td></tr>
    <tr><td class="k">Logica</td><td><code>App\Support\Presentiebewaking</code> — percentage, norm per student, volledigheid per week. <code>App\Support\Statistiek</code> levert de dashboard-aggregaties.</td></tr>
  </table>
  <div class="let"><b>Ontbrekende registratie is geen afwezigheid.</b> Het percentage wordt berekend over de <b>geregistreerde</b> weken. Een week zonder regel telt niet mee — anders zou nalatigheid van de docent op de student worden afgewenteld. Een week geldt pas als “geregistreerd” wanneer álle presentieplichtige deelnemers een waarde hebben.</div>
  <p>Vrijgestelde studenten (<code>vaktoewijzingen.vrijgesteld</code>) volgen het vak niet en worden bij het opslaan overgeslagen, óók als het formulier voor hen een waarde meestuurt. Wijzigt u <code>weken_per_blok</code>, dan blijven bestaande registraties met een hoger weeknummer in de database staan maar verdwijnen zij uit het scherm; ruim ze in dat geval expliciet op.</p>

  <h2>8. Collegegeld: termijnen</h2>
  <p>Er is bewust <b>geen facturentabel</b>. Het termijnschema wordt volledig afgeleid uit het jaartarief, de betaalregeling en de inschrijvingsduur, zodat het nooit kan verouderen ten opzichte van de inschrijving (bijvoorbeeld na een gewijzigde uitschrijfdatum).</p>
  <table class="kv">
    <tr><td class="k">Schema</td><td><code>App\Support\Collegegeldtermijnen</code> — vervalmaanden 9, 11, 1, 3, 5; bedrag = jaarbedrag ÷ n, restje op de laatste termijn.</td></tr>
    <tr><td class="k">Regeling</td><td><code>inschrijvingen.betaalregeling</code>: <code>termijnen</code> (5 facturen) of <code>volledig</code> (1 factuur, vervalt 1 september).</td></tr>
    <tr><td class="k">Betaling &rarr; termijn</td><td><code>betalingen.termijn</code> (1..5, nullable). Leeg = automatisch toerekenen aan de oudste openstaande termijn (FIFO).</td></tr>
    <tr><td class="k">Achterstand</td><td>Som van het openstaande deel van de termijnen waarvan de <b>vervaldatum verstreken</b> is. Dit stuurt de blokkades op herinschrijven en verklaringen.</td></tr>
  </table>
  <div class="let"><b>Per opleiding (beleid 2026-07-10).</b> Collegegeld hangt aan de INSCHRIJVING, niet aan het studiejaar. Elke inschrijving heeft een eigen termijnschema en eigen betalingen; <code>Collegegeldstatus::voor()</code> telt alle inschrijvingen op. Korting staat op <code>inschrijvingen.korting_percentage</code> (+ verplichte <code>korting_reden</code>); <code>Collegegeldstatus::jaarbedrag()</code> geeft het tarief ná korting en is de basis voor het schema. Bij 100% korting levert <code>Collegegeldtermijnen::voor()</code> een lege collectie. Betalingen worden nooit tussen opleidingen verrekend. Een achterstand bij één inschrijving zet <code>achterstand</code> op true en blokkeert herinschrijven en verklaringen.</div>
  <div class="let"><b>Schuld en blokkade zijn twee dingen.</b> <code>Collegegeldstatus::voor()</code> geeft <code>achterstand</code> (er is een onbetaalde vervallen termijn) én <code>geblokkeerd</code> (<code>achterstand</code> zonder lopende betalingsafspraak). Blokkeer altijd op <code>geblokkeerd</code> / <code>isGeblokkeerd()</code>, nooit op <code>achterstand</code> — dat laatste beschrijft alleen de schuld en blijft true tijdens een afspraak. Tabel <code>betalingsafspraken</code> (<code>geldig_tot</code>, <code>reden</code>, <code>vastgelegd_door_id</code>, <code>ingetrokken_op</code>). 'Lopend' is afgeleid (<code>Betalingsafspraak::isLopend()</code>), nooit een opgeslagen status: een verlopen of ingetrokken afspraak kan zo niet blijven doorwerken. Vastleggen/intrekken alleen door rollen <code>financien</code> en <code>beheerder</code>; beide gelogd (<code>veld: betalingsafspraak</code>).</div>
  <div class="let"><b>Betalingen zijn muteerbaar.</b> <code>BetalingController::bijwerken()</code> en <code>::verwijderen()</code> (rollen financien + beheerder) schrijven de oude en nieuwe waarden naar de audit-log (<code>veld: betaling</code>). Verwijder deze logging niet: zij is het enige bewijsspoor van mutaties op geldbedragen.</div>
  <div class="let"><b>Synthetische studenten opschonen.</b> Artisan-commando <code>php artisan sis:studenten-verwijderen {nummers*} {--force}</code> verwijdert studenten op studentnummer (los of als reeks, bijv. <code>261015-261026</code>). Zonder <code>--force</code> toont het alleen een voorbeeld (dry-run); met <code>--force</code> verwijdert het definitief, in een transactie en per student gelogd (<code>AuditLogger::VERWIJDERING</code>, veld <code>student</code>). Alle gekoppelde gegevens verdwijnen via de database-constraints: inschrijvingen, betalingen, resultaten, presenties, vaktoewijzingen, notities, documenten, vrijstellingsbesluiten, betalingsafspraken en kennistoetsresultaten staan op <code>ON DELETE CASCADE</code>; <b>taken</b> en <b>ondertekende documenten</b> op <code>SET NULL</code> (die blijven bestaan, alleen de koppeling vervalt). NB: de studententabel heet <code>studenten</code> (niet <code>students</code>). Uitsluitend bedoeld voor het opschonen van synthetische testdata.</div>
  <div class="let"><b>Jaarovergang (actief studiejaar).</b> Het systeem is periode-geparametriseerd: precies één <code>perioden</code>-rij heeft <code>actief = true</code>, afgedwongen door het <code>Periode::saved</code>-event (activeren van één jaar deactiveert de rest). Alle runtime-logica leidt het jaar af uit de <b>periode van de inschrijving</b> of uit <code>now()</code> — nergens staat een studiejaar hardcoded in een berekening. Termijndata (<code>Collegegeldtermijnen</code>) komen uit <code>periode.startdatum</code>; het tarief uit <code>collegegeldtarieven</code> op <code>periode_id</code> (opleiding-specifiek vóór een eventueel <code>opleiding_id = null</code> standaardtarief van dezelfde periode). De jaarovergang naar 2026-2027 op de draaiende DB is gezet met de guarded datamigratie <code>activeer_studiejaar_2026_2027</code> (raw update — een migratie vuurt geen Eloquent-event; daarom expliciet alle jaren op inactief en daarna 2026-2027 op actief). Op een verse migratie draait die vóór de seeders (lege <code>perioden</code>) en doet niets, zodat de <code>ReferentieSeeder</code>-basislijn (2025-2026 actief) en de tests ongewijzigd blijven. Beheer stuurt verdere overgangen via Opzoektabellen → Studiejaren (<code>ReferentieController</code>, checkbox <code>actief</code>). Let op: <code>RapportController::actieveStudentenExport</code> filtert op <code>status = actief</code> over ALLE perioden, niet op de actieve periode — bewust, zodat na de overgang zowel herinschreven als nog-niet-herinschreven actieve studenten meekomen.</div>
  <div class="let"><b>Let op de betekenis van de velden</b> in <code>Collegegeldstatus::voor()</code>: <code>verschuldigd</code> is het totaal van de niet-vervallen termijnen (bij een lopende inschrijving dus het volle jaarbedrag), <code>openstaand</code> is verschuldigd − betaald (inclusief termijnen die nog moeten vervallen), en <code>achterstallig</code> is het direct opeisbare bedrag. Alleen <code>achterstallig</code> bepaalt <code>achterstand</code>.</div>
  <p>De kolom <code>inschrijvingen.betaalwijze</code> is <b>vervallen</b> (zij mengde regeling en betaalwijze) en blijft alleen voor de historie bestaan. De betaalwijze hoort bij een betaling, niet bij de inschrijving.</p>
  <p>Bij een beëindigde inschrijving wordt het totaal herrekend naar het pro rata bedrag; termijnen met een vervaldatum ná het einde worden <code>vervallen</code> en de laatste geldende termijn vangt het verschil op. De CSV-import herkent kolommen op <b>naam</b> uit de kopregel, met terugval op de klassieke volgorde (<code>studentnummer;bedrag;datum;betaalwijze;opmerking</code>) voor oudere bestanden.</p>

  <h2>9. Curriculum (vakken)</h2>
  <table class="kv">
    <tr><td class="k">Bron</td><td><code>database/data/curriculum.csv</code> (uit 'vakkenlijst update.xlsx', 2026-07-10), geladen door <code>CurriculumSeeder</code>. Referentiedata, geen persoonsgegevens: hoort in Git.</td></tr>
    <tr><td class="k">Herladen</td><td><code>php artisan db:seed --class=CurriculumSeeder</code> — idempotent: matcht op (opleiding, code) en werkt bij. Vakken die niet in de CSV staan blijven ongemoeid.</td></tr>
    <tr><td class="k">EC</td><td><code>vakken.ec</code> is <code>decimal(4,1)</code>: halve studiepunten (2,5) komen voor. Nooit naar <code>int</code> casten. Gebruik <code>App\Support\Ec::toon()</code> voor weergave (Nederlandse komma).</td></tr>
    <tr><td class="k">Vakcode</td><td>Uniek per opleiding: <code>unique(opleiding_id, code)</code>. Elf codes bestaan in zowel ISLTH als PMGV.</td></tr>
    <tr><td class="k">Blok</td><td><code>null</code> = het vak loopt het hele studiejaar (stage, scriptie). Views die over blok 1..4 itereren moeten blok <code>null</code> apart tonen.</td></tr>
    <tr><td class="k">Keuzevak</td><td><code>vakken.keuzevak</code>. <code>Vaktoewijzer</code> slaat keuzevakken over; <code>Overgangsbeoordeling</code> telt alleen de keuzevakken mee die aan de inschrijving zijn toegewezen.</td></tr>
  </table>
  <div class="let"><b>Synthetische vakken horen niet naast het echte curriculum.</b> De voorbeeldvakken met code <code>ISLTH-*</code> zijn verplaatst naar <code>SynthetischVakSeeder</code>, die alleen door de testsuite wordt gebruikt en bewust NIET in <code>DatabaseSeeder</code> staat. Stonden zij actief naast het echte curriculum, dan telden zij mee in de EC-totalen per leerjaar (jaar 1 werd 94 i.p.v. 60 EC) en werden zij automatisch aan elke ISLTH-student toegewezen.</div>
  <p>Nog te doen: de nieuwe vakken hebben <b>geen docent</b> gekoppeld (<code>vakken.docent_id</code> is leeg) en krijgen elk één standaard toetsonderdeel ('Tentamen', weging 100%). Koppel de docenten en verfijn de toetsopbouw via <b>Vakstructuur</b> voordat docenten cijfers gaan invoeren; zonder docentkoppeling blijft 'Mijn vakken' leeg.</p>

  <h2>10. Takenlijst (Studentenzaken)</h2>
  <p>Tabel <code>taken</code>, model naar Outlook Taken / Microsoft Graph <code>todoTask</code>: <code>titel</code>, <code>omschrijving</code>, <code>startdatum</code>, <code>vervaldatum</code>, <code>status</code> (open | bezig | afgerond), <code>prioriteit</code> (laag | normaal | hoog), <code>afgerond_op</code>, <code>afgerond_door_id</code>. Optionele koppelingen: <code>student_id</code>, <code>toegewezen_aan_id</code>, <code>aangemaakt_door_id</code>, <code>afgerond_door_id</code> — alle vier <code>nullOnDelete</code>, zodat een taak blijft bestaan als een student of medewerker verdwijnt.</p>
  <p><code>afgerond_op</code> en <code>afgerond_door_id</code> volgen altijd de status: bij afronden worden beide gezet, bij heropenen beide op <code>null</code>. Dat gebeurt zowel via de afvinkknop als via het bewerkformulier. De afvinker is bewust een eigen veld en niet <code>toegewezen_aan_id</code>: een niet-toegewezen taak mag door iedereen worden opgepakt, en een collega mag andermans taak afronden. Bij taken die vóór deze kolom werden afgerond blijft het veld leeg; dat wordt niet met terugwerkende kracht ingevuld.</p>
  <div class="let"><b>'Te laat' is geen kolom.</b> De status wordt afgeleid uit <code>vervaldatum &lt; vandaag</code> én <code>status != afgerond</code> (<code>Taak::isTeLaat()</code>). Sla dit nooit op: anders kan een afgeronde taak in de database als 'te laat' blijven staan.</div>
  <p>Toegang uitsluitend voor Studentenzaken en Beheer (<code>Rol::magTakenBeheren()</code>, routegroep <code>rol:studentenzaken,beheerder</code>). Er is bewust <b>geen audit-logging</b>: een taak is werkverdeling, geen gevoelig persoonsgegeven. Wel blijft zichtbaar wie de taak aanmaakte en aan wie zij is toegewezen.</p>

  <h2>11. Regulier onderhoud</h2>
  <ul>
    <li><b>Migraties:</b> <code>php artisan migrate</code> na een update (reversible; maak eerst een back-up).</li>
    <li><b>Cache:</b> <code>php artisan optimize:clear</code> bij onverwacht gedrag na wijzigingen.</li>
    <li><b>Logs:</b> <code>storage/logs/</code> (zitten niet in de back-up).</li>
    <li><b>Tests:</b> <code>php artisan test</code> moet groen zijn vóór uitrol.</li>
  </ul>

  <div class="tip">Deze handleiding wordt bijgewerkt zodra er functies of infrastructuur wijzigen. Controleer de datum onderaan op actualiteit.</div>
</body>
</html>
