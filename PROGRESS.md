# PROGRESS.md — IUASR Intern Studentbeheersysteem (SIS)

Continuiteitsbestand tussen sessies. Werk dit bij aan het einde van elke sessie.
Bouw per fase; ga nooit een fase vooruit zonder akkoord van de opdrachtgever.

---

## Projectstatus

- **Huidige fase:** Fase 5 afgerond; aanwezigheids- en collegegeldtermijnmodule
  opgeleverd (buiten de oorspronkelijke fasering, op verzoek van de opdrachtgever).
  Module Cursussen Administratie afgerond; module **Relatiebeheer & Stagebeheer**
  volledig afgerond (Fasen A–H); module **HR / Personeelszaken** volledig
  afgerond (Fasen A–G: self-service "Mijn HR" + iCal-agenda, en Fase G met
  globaal zoeken en signaleringen — aflopende contracten + verzuim volgens de
  Wet Verbetering Poortwachter). Daarna op verzoek
  opdrachtgever: **HR-medewerker en Manager
  samengevoegd tot één rol** en het **Schoolbestuur één samengevoegd overzicht**
  met de statistieken van alle modules (vijf rubrieken). Daarna (2026-07-11):
  **multi-rol per gebruiker** (primaire rol + extra rollen, rechten als unie) en
  een **aanmaakscherm voor gebruikers** (Beheerder); en een **studiegids-analyse**
  (BA ISLTH 2025-2026) met aanbevolen fixes: aanwezigheidsnorm 75%, instelbaar
  EC-model (knock-out/compensatorisch), aanwezigheidssignaal bij de cijferinvoer.
  Zie het beslissingenlogboek voor de openstaande OER-punten.
- **Laatst bijgewerkt:** 2026-07-11
- **Repo:** git@github.com:eraduz/iuasr-sis.git (gepusht naar `main`)

---

## Faseoverzicht

Elke fase eindigt bij een verifieerbaar opleverpunt. Zet het vinkje pas als dat
opleverpunt aantoonbaar klaar is.

- [ ] **Fase 0 — Fundament & AVG-nulmeting** (in uitvoering)
  - Git-repo, remote, AVG-veilige `.gitignore`, PROGRESS.md, leeg mappenskelet.
  - Nog te doen: DPIA-opzet en synthetische seed-dataset (latere sessie).
- [~] **Fase 1 — Functioneel Ontwerp (FO) + datamodel** (grotendeels belegd in code)
  - Genormaliseerd datamodel met surrogaatsleutels, rolmodel, gevoelige-data-plan.
  - Gedaan: migraties (student, inschrijving, vak, toetsonderdeel, resultaat,
    opzoektabellen, audit-log), Eloquent-modellen, `Rol`-enum, EC-logica.
  - Nog te doen: FO-document in `docs/`, datamodel-diagram, formele vaststelling.
- [~] **Fase 2 — Technisch Ontwerp (TO)** (aanzet gebouwd)
  - Laravel-projectopzet, Entra ID/OIDC-auth, InnoDB-schema, migratiestrategie.
  - Gedaan: Laravel 12-skelet, config (`sis.php`, `database.php`), rolscheiding
    (Gates + `rol`-middleware), Blade-layout gekoppeld aan het leidende design
    system, synthetische seeders, unit-test rolscheiding.
  - Gedaan (deze sessie): PHP 8.3.32 + Composer 2.10 + portable MariaDB 11.4.9
    geïnstalleerd (user-profiel, geen admin); `composer install`,
    `php artisan migrate --seed` en de testsuite draaien groen; de app boot op
    `http://127.0.0.1:8000` (dashboard + SSO-login geven HTTP 200).
  - Nog te doen: Entra ID/OIDC-integratie, TO-document.
- [~] **Fase 3 — Kern-CRUD** (increment 1 + 2 opgeleverd)
  - Student/inschrijving/opleiding-beheer door SZ (identiteit, geen cijfers).
  - Increment 1: dev-login (tijdelijk, vervangt Entra later), server-side
    rolscheiding op alle routes; server-gerenderde shell; studentenlijst (zoek op
    studentnummer), studentdetail (BSN gemaskeerd + gelogde inzage; cijfer-tabblad
    afgeschermd voor SZ), inschrijven met automatische nummergeneratie.
  - Increment 2: lifecycle via `InschrijvingStatus`-enum (incl. geschorst) —
    muteren, herinschrijven (studentnummer blijft), uitschrijven (uitschrijfdatum
    = einde maand), schorsen/opheffen met één klik; verklaringen (A4, geen
    cijfers/BSN, uitgifte gelogd); opzoektabellen-beheer (generieke CRUD voor
    opleidingen, vakken, perioden, klassen, docenten, landen, nationaliteiten);
    gebruikers & rollen (toegangsmatrix + rol wijzigen, gelogd); audit-log
    (alleen-lezen); klassenlijst-rapport (A4). Rijkere synthetische dataset.
  - Interne notities per student (datum + auteur), direct onder de Contact-kaart
    op het studentdetail; alleen zichtbaar/te bewerken voor Studentenzaken & Beheer.
  - Studentenlijst met filterbalk (status + opleiding); standaard alleen ACTIEVE
    studenten (uitgeschrevenen niet in beeld tenzij expliciet gekozen). Menu-item
    heet "Alle studenten".
  - 27 tests groen (rolscheiding, lifecycle, mutatie, verklaring, beheer, notities,
    studentenlijst-filter); alle schermen live geverifieerd.
  - Excel-export (Rapporten): alle ACTIEF ingeschreven studenten met alle
    gegevens in .xlsx (PhpSpreadsheet), inclusief IBAN voor boekhouding/facturatie
    en ZONDER BSN. Waarden als tekst (IBAN/telefoon/postcode blijven intact).
    Toegang: Studentenzaken, Financiën, Beheerder; export gelogd. Ook een knop op
    het Financiën-scherm.
  - Bulk-inschrijving: CSV-export van het aanmeldportaal in bulk inschrijven
    (Studentenzaken/Beheerder). Header-gebaseerde kolomherkenning (tolerant voor
    `;`/`,` en Nederlandse kopnamen), controlestap (wat wordt ingeschreven / wat
    overgeslagen: onbekende opleiding, duplicaat op naam+geb.datum of e-mail),
    daarna definitief inschrijven met automatisch studentnummer + vaktoewijzing.
    BSN wordt NIET geïmporteerd (AVG). Sjabloon-download aanwezig.
  - Aanmeldportaal-velden: huisnummer, provincie, land, onderwijsinstelling +
    afstudeerjaar vorige opleiding toegevoegd aan wijzigscherm en studentpagina
    (straat=adres, stad=woonplaats, IBAN=rekeningnummer bestonden al).
  - Documentmodule: per student min. 6 documenten (identiteitsbewijs voor/achter,
    diploma, cijferlijst, pasfoto, overig) uploaden, bekijken (inline) en
    downloaden; bestanden op de PRIVATE schijf (buiten webroot), inzage/afgifte
    gelogd; alleen Studentenzaken/Beheer.
  - "Student levert later aan"-vinkje bij de documenten → dashboardtegel
    (signaleringen) toont welke studenten diploma/documenten nog moeten aanleveren.
  - Digitale documentondertekening (hash-gebaseerd, increment 1): gegenereerde
    PDF's (dompdf) krijgen automatisch een handtekeningblok met verificatiecode
    en SHA-256-echtheidskenmerk; gearchiveerd op de private schijf met
    logregistratie (wie/wanneer/aan wie). Verklaringen worden nu als ondertekende
    PDF gegenereerd (ontvanger verplicht). Archief 'Ondertekende documenten'
    (Beheerder/Directie/Studentenzaken) + publieke verificatiepagina (/verificatie,
    geen login) met optionele bestand-hashcontrole + WordPress-shortcode.
  - Ondertekenmodule increment 3 (privacy/rolscheiding): het archief toont voor
    elke gebruiker alleen de EIGEN ondertekende documenten. Nieuwe rol
    Schoolbestuur (Bestuur) + Beheerder zien ALLES; Directie (opleidingsdirecteur)
    en Studentenzaken zien uitsluitend hun eigen documenten. Eigenaarscontrole
    ook op download/waarmerk/resultaatscherm (403 bij andermans document).
  - Ondertekenmodule increment 2: eigen PDF uploaden en laten waarmerken
    (Studentenzaken, Directie=opleidingsdirecteuren/bestuur, Beheerder). Het
    origineel blijft ongewijzigd (werkt voor elk PDF-formaat), krijgt een SHA-256
    + verificatiecode en een apart digitaal waarmerk-certificaat (PDF). Origineel
    én waarmerk downloadbaar uit het archief; uitgifte gelogd. Nog te doen:
    auto-sign van overige gegenereerde PDF's, eventueel PAdES-ondertekening zodra
    er een certificaat is.
  - NT2-bewaking: NT2-plichtige studenten hebben 1 jaar vanaf de inschrijfdatum
    om het examen te halen. Deadline wordt afgeleid; `nt2_behaald_op` vastlegbaar
    via het wijzigscherm. Dashboardvenster bij Studentenzaken toont de openstaande
    gevallen (verstreken / binnen 30 dagen / open) op urgentie gesorteerd.
  - Nog te doen: cijferrapport/tentamenlijst (na Fase 4), student toevoegen los
    van aanmelding is al mogelijk via inschrijven.
- [x] **Fase 4 — Cijfers + rolscheiding** (afgerond, increment 1 t/m 6)
  - Genormaliseerde resultaatregels, docent-invoer eigen vak, server-side autorisatie.
  - Gedaan: docent → Mijn vakken + Cijferinvoer (grid met deelresultaten, weging,
    poging, vrijstelling; live gewogen eindcijfer, cesuur 5,5); EC-toekenning
    (alle meetellende onderdelen ≥ cesuur ⇒ vak-EC, anders 0); cijferoverzicht
    (Examencie/Directie, inzage + gelogd); cijfer-tabblad op studentdetail gevuld;
    audit-logging op invoer/wijziging en inzage. Rolscheiding: SZ geen toegang,
    docent alleen eigen vak, examencie/directie read-only. 36 tests groen.
  - Increment 2: vaststellingsworkflow via `Cijferlijst` (vak × periode), status
    concept → ingediend → vastgesteld. Docent dient in (daarna vergrendeld);
    examencommissie stelt vast of stuurt terug (met opmerking) en kan een
    vastgestelde lijst corrigeren (gelogd). Resultaten worden bij vaststellen
    definitief. Status zichtbaar in mijn-vakken, cijferoverzicht en op het
    examencommissie-dashboard ("ter vaststelling"). 52 tests groen.
  - Increment 3: pro rata collegegeld. `Collegegeldstatus` berekent verschuldigd =
    jaartarief ÷ 12 × maanden ingeschreven (studiejaar 1 sep – 31 jul;
    uitschrijfdatum t/m einde uitschrijfmaand; lopende inschrijving telt t/m de
    huidige maand). Studentpagina en Financiën tonen jaarbedrag, maandbedrag,
    maanden, verschuldigd, betaald en het saldo (openstaand óf terugbetaling).
    Financiën-overzicht heeft aparte lijsten voor achterstanden en
    terugbetalingen; het uitschrijfformulier berekent het gevolg live. Het
    actieve studiejaar is verzet naar 2025-2026 zodat "vandaag" binnen het
    lopende jaar valt. 54 tests groen (o.a. pro rata uitschrijven en
    terugbetaling).
  - Increment 4: bulk-import van betalingen via CSV (Financiële Administratie).
    Upload op het Financiën-scherm met downloadbaar sjabloon; detecteert `;`/`,`
    als scheidingsteken en Nederlandse bedragnotatie, koppelt per studentnummer
    aan de meest recente inschrijving, en toont een samenvatting (geïmporteerd /
    overgeslagen met reden per regel). Geen externe dependency (fgetcsv). 61
    tests groen.
  - Increment 5: herkansing als APARTE poging in de cijferinvoer (per onderdeel
    1e poging + herkansing als losse resultaatregels; beste telt voor eindcijfer
    en EC).
  - Increment 6: leerjaar-herbeoordeling / overgangsadvies. `Overgangsbeoordeling`
    telt behaalde EC per student en toetst aan de EC-overgangsdrempel per
    opleiding (positief / voorwaardelijk ≥75% / negatief). Rapport voor
    Examencommissie en Directie, filterbaar op opleiding/leerjaar. EC-drempel
    default 30 EC (landelijke BSA-norm vanaf 2026-2027), per opleiding aanpasbaar
    via Opzoektabellen.
  - Fase 4 afgerond. 87 tests groen.
- [x] **Fase 5 — Rapporten + documenten** (afgerond, increment 1 t/m 3)
  - Al eerder gebouwd: klassenlijst, alumni-rapport, Excel-export actieve
    studenten (incl. IBAN), overgangsadvies, verklaringen-PDF + digitale
    ondertekening.
  - Increment 1: officiële cijferlijst / transcript per student. `Transcript`
    bouwt per studiejaar de vakken met eindcijfer, EC en status op. Scherm met
    zoekfunctie + per-studiejaar tabellen en totaal behaalde EC; downloadbaar als
    ONDERTEKENDE PDF op het IUASR-briefpapier (past op briefpapier, gelogd).
    Cijferinzage → Examencommissie en Directie (nooit Studentenzaken → 403).
    Menu-item + Rapporten-kaart 'Cijferlijst' geactiveerd.
  - Increment 2: tentamenlijst per vak. Read-only overzicht van deelnemers +
    resultaten (beste poging per onderdeel, eindcijfer, EC, status) met
    samenvatting (deelnemers/geslaagd/gemiddelde); printbaar en downloadbaar als
    ondertekende PDF op briefpapier. Toegang: docent (eigen vak), examencommissie,
    directie; links vanaf cijferoverzicht en cijferinvoer.
  - Increment 3: EC-rapport. Studievoortgang per opleiding/klas — cumulatief
    behaalde EC per student t.o.v. het nominale totaal, met voortgangsbalk,
    filter op opleiding/leerjaar/klas en gemiddelde. Voor Examencommissie en
    Directie; Rapporten-kaart + menu geactiveerd.
  - Fase 5 afgerond. 95 tests groen.
- [x] **Aanwezigheidsmodule** (extra, op verzoek opdrachtgever 2026-07-09)
  - **50%-aanwezigheidsregeling**: vinkje op de INSCHRIJVING
    (`inschrijvingen.aanwezigheidsregeling_50`), dus per opleiding én studiejaar;
    bij herinschrijven bewust opnieuw toe te kennen. Studentenzaken/Beheer zetten
    het vinkje (met toestemming van de directie, buiten het systeem); mutatie
    gelogd. Zichtbaar voor SZ, Docent, Examencie, Directie, Bestuur, Beheer —
    niet voor Financiën. Dashboardvenster "Studenten met 50%-aanwezigheidsregeling"
    (docent: alleen eigen vakken; directie: alleen eigen opleidingen).
  - **Presentieregistratie** (verplicht voor de docent): tabel `presenties`
    (inschrijving × vak × week), 8 onderwijsweken per blok, waarde 1 = aanwezig,
    0 = afwezig, geen regel = niet geregistreerd (telt NIET als afwezigheid).
    Vrijgestelde studenten worden overgeslagen (server-side afgedwongen).
    Norm 80%, of 50% bij de regeling; de docent ziet het label 50% op de lijst.
  - Schermen: aanwezigheidslijst per vak (invoergrid met "alle 1"-kolomactie,
    live percentage, printbaar), aanwezigheidsoverzicht per rol, kolom
    "Aanwezigheid" op Mijn vakken, dashboardvenster "Aanwezigheidsregistratie nog
    niet volledig" (docent/directie/bestuur) met de ontbrekende weken.
  - Statistiek: gemiddelde aanwezigheid, verdeling in banden (0–50 / 50–80 /
    80–100%), per opleiding (bestuur) en per vak (directie/docent); KPI-tegel op
    het directie-/bestuursdashboard. Alles opleidinggebonden gefilterd.
  - Rolscheiding: registreren = docent eigen vak (Gate `presentie-registreren`);
    inzage = docent/examencie/directie(eigen opleiding)/bestuur (Gate
    `presentie-inzien`). SZ, Financiën en Beheer hebben GEEN presentie-inzage.
    Inzage en mutatie gelogd. 18 nieuwe tests; 167 tests groen.
  - Naamgeving: UI zegt "Aanwezigheid"; de bestaande term *presentielijst* blijft
    gereserveerd voor de tentamenlijst met handtekening.
- [x] **Collegegeldtermijnen** (extra, op verzoek opdrachtgever 2026-07-10)
  - Facturering elke twee maanden: **september, november, januari, maart, mei**.
    Termijnbedrag = jaarbedrag ÷ 5, afrondingsrestje op de laatste termijn.
  - **Betaalregeling** per inschrijving (`inschrijvingen.betaalregeling`):
    `termijnen` (5 facturen) of `volledig` (1 factuur, vervalt 1 september) —
    voor studenten die alles in één keer willen betalen. Studentenzaken legt dit
    vast op het dossier en in het inschrijfformulier; gelogd.
  - **Geen facturentabel**: het schema wordt afgeleid uit jaartarief +
    betaalregeling + inschrijvingsduur (`App\Support\Collegegeldtermijnen`), zodat
    het nooit veroudert t.o.v. de inschrijving. Een betaling verwijst met
    `betalingen.termijn` (1..5, nullable) naar het termijnnummer; leeg = FIFO naar
    de oudste openstaande termijn.
  - **Achterstand herdefinieerd**: alleen het openstaande deel van termijnen
    waarvan de VERVALDATUM verstreken is (`achterstallig`). Nog niet vervallen
    termijnen zijn géén achterstand. Dit stuurt de blokkades op herinschrijven en
    verklaringen. `verschuldigd` = som niet-vervallen termijnen (lopend = heel
    jaarbedrag); `openstaand` = verschuldigd − betaald.
  - **Tussentijdse uitschrijving**: termijnen na de uitschrijfdatum worden
    `vervallen`; het totaal wordt herrekend naar pro rata en de laatste geldende
    termijn bijgesteld (keuze opdrachtgever).
  - Schermen: termijntabel op het studentdossier (kaart Collegegeld) en op het
    Financiën-scherm, daar met een **"Boek € …"-knop per openstaande termijn**;
    betalingsformulier met termijnkeuze; Financiën-overzicht toont achterstallig.
  - CSV-import: extra optionele kolom `termijn`; kolommen worden nu op **naam**
    herkend met terugval op de oude volgorde, zodat bestaande bestanden blijven
    werken (getest).
  - `inschrijvingen.betaalwijze` is vervallen (mengde regeling en betaalwijze);
    kolom blijft voor historie, wordt niet meer geschreven.
  - 19 nieuwe tests + 2 importtests; 189 tests groen.
- [x] **Echt curriculum ingeladen** (2026-07-10, bron 'vakkenlijst update.xlsx')
  - 91 vakken: ISLTH (60), PMGV (12), MGV (19). **PABO volgt later.**
  - Bron in Git: `database/data/curriculum.csv` + `CurriculumSeeder` (idempotent,
    matcht op opleiding+code). Geen persoonsgegevens, dus AVG-veilig in de repo.
  - Schema-wijzigingen die hiervoor nodig waren:
    * `vakken.ec` smallint → **decimal(4,1)**: 28 vakken hebben 2,5 EC.
      Ook `vaktoewijzingen.vrijstelling_ec`. Alle `(int)`-casts op EC vervangen;
      nieuwe helper `App\Support\Ec::toon()` (Nederlandse komma: "2,5", "5").
    * `vakken.code` uniek per opleiding: **unique(opleiding_id, code)**. Elf codes
      (o.a. B-QR02, B-KL01) bestaan in zowel ISLTH als PMGV — aparte vakken met
      eigen cijferlijst, docent en presentielijst.
    * Nieuwe kolom `vakken.keuzevak`: keuzeruimte wordt NIET automatisch
      toegewezen (`Vaktoewijzer` slaat ze over). Bachelor jaar 4 = 40 EC verplicht
      + 55 EC keuzeruimte (student kiest 20 EC). `Overgangsbeoordeling` telt
      alleen de keuzevakken mee die daadwerkelijk zijn toegewezen.
    * `blok` mag leeg zijn = het vak loopt het **hele studiejaar** (stages,
      scripties, M-GV16a/b). Vakstructuur en vaktoewijzing tonen dat nu apart;
      voorheen liepen die views hard van blok 1 t/m 4 en waren jaarvakken onzichtbaar.
  - Beslissingen bronlijst: tekstkolom 'Blok' is leidend bij tegenstrijdigheid
    (B-SC07 → blok 4, B-AR06-15 → blok 2); `B-FQ02-B-FQ03` blijft één vak zonder
    vast blok. PMGV-totaal gecorrigeerd naar 50 EC (was 60) — zie 2026-07-10.
  - **9 synthetische vakken (ISLTH-*) definitief verwijderd** uit de database, incl.
    90 vaktoewijzingen, 15 resultaten, 94 presenties, 5 cijferlijsten en 3
    vrijstellingsbesluiten (op verzoek opdrachtgever). Ze stonden actief naast het
    echte curriculum en maakten van jaar 1 94 EC i.p.v. 60. Ze zijn verhuisd van
    `ReferentieSeeder` naar `SynthetischVakSeeder` — uitsluitend testfixture, niet
    in `DatabaseSeeder`. Alle betrokken tests seeden die fixture nu expliciet.
  - **Nog te doen:** docenten koppelen aan de 91 vakken (`docent_id` is leeg, dus
    'Mijn vakken' is voor docenten leeg) en de toetsopbouw verfijnen — elk vak
    kreeg één standaard toetsonderdeel 'Tentamen' (weging 100%).
  - 13 nieuwe tests (`CurriculumTest`); 202 tests groen.
- [x] **Takenlijst Studentenzaken** (extra, op verzoek opdrachtgever 2026-07-10)
  - Model naar **Outlook Taken / Microsoft Graph `todoTask`**, teruggebracht tot het
    hoogstnodige: `titel`, `omschrijving`, `startdatum`, `vervaldatum`,
    `status` (open/bezig/afgerond), `prioriteit` (laag/normaal/hoog), `afgerond_op`.
  - **Gedeelde afdelingslijst** met toewijzing: een taak zonder toegewezene is vrij
    op te pakken. Optionele koppeling aan een studentdossier (`student_id`).
  - **'Te laat' is afgeleid**, geen kolom: vervaldatum verstreken én niet afgerond.
    Zo kan een afgeronde taak nooit als te laat blijven staan.
  - Schermen: takenlijst met filters (openstaand/status/alleen mijn taken/zoeken),
    inline bewerken, afvinken met één klik; kaart **Taken** op het studentdossier met
    snelinvoer; dashboardvenster **Mijn taken** (eigen + vrije taken, vervaldatum
    binnen 7 dagen of verstreken, op urgentie).
  - Toegang: uitsluitend Studentenzaken en Beheer (`Rol::magTakenBeheren()`).
    Geen audit-logging — werkverdeling, geen gevoelig persoonsgegeven.
  - Afgeronde taken staan in een aparte sectie onder de werkvoorraad, met de
    datum en **wie de taak heeft afgevinkt** (`afgerond_door_id`). Dat is niet per
    se degene aan wie de taak was toegewezen. Bij heropenen vervallen beide velden.
  - 20 nieuwe tests (`TakenTest`); 222 tests groen.
- [~] **Multi-module platform** (nieuwe richting, opdrachtgever 2026-07-10:
  "Prompt voor Ai tweede ronde.docx"). Het systeem groeit van een
  Studentenzaken-app naar een platform met meerdere modules (Studentenzaken,
  Cursussen Administratie, en later Stage, Scriptie, HR). Beslissingen: bestaande
  rol-enum uitbreiden (geen RBAC-herbouw); cursisten in een EIGEN tabel;
  moduletoegang afgeleid uit de rol; iDEAL nu alleen als betaalmethode/-status,
  geen live provider.
  - [x] **Fase A — Platformfundament.** Tabel `modules` (registry, 5 modules;
    alleen Studentenzaken actief). Keuzescherm na de login (`/modules`,
    `modules.kiezen`); dev-login redirect daarheen. `Rol::moduleSleutels()` +
    `Module::toegankelijkVoor()/bruikbaarVoor()/startRoute()`. Financiën en Beheer
    zien meerdere modules; onderwijsrollen alleen Studentenzaken. Nog niet
    gebouwde modules = 'Binnenkort' (grijs). Terugknop 'Modules' in de header.
    8 tests (`ModulekeuzeTest`); 274 groen. Handleidingen bijgewerkt.
  - [x] **Fase B — Cursussen & cursisten.** Rol **Cursusadministratie** toegevoegd
    (enum + ALTER); Cursussen-module op actief. Tabellen `cursussen` (3 cursussen:
    Arabische Taal € 265, Hifz € 330, Ijaaza € 430; uitbreidbaar), `cursisten`
    (lichtere kopie van studenten, eigen cursistnummer C+jaar+volgnr) en
    `cursusinschrijvingen` (totaalbedrag = momentopname cursusgeld). Cursusbeheer-CRUD,
    cursisten handmatig + **bulk-import (.xlsx/.csv via Tabellezer)**, inschrijven met
    statusbeheer. Eigen cursus-sidebar; cursus-only rollen worden van / naar de module
    gestuurd. Rolscheiding: alleen Cursusadministratie + Beheer. Alles gelogd. 13 tests
    (`CursussenModuleTest`); 287 groen. Cursusdirecteur-rol volgt in Fase D.
  - [x] **Fase C — Cursusgelden & boekhouding.** Tabel `cursusbetalingen`
    (bedrag, methode, datum, status, referentie, opmerking, geregistreerd-door).
    Enums **Betaalmethode** (iDEAL/online, bankoverschrijving, contant) en
    **Cursusbetaalstatus** (in afwachting/betaald/mislukt/terugbetaald; alleen
    *Betaald* telt mee). Financiële status per inschrijving is **afgeleid, niet
    opgeslagen** (`Cursusgeldstatus::voor()` → totaal/betaald/openstaand +
    voldaan/deels/open). De **Financiële Administratie** (boekhouding) opent de
    pagina **Cursusgelden**: filteren (cursus/status/zoek), betaling registreren,
    **wijzigen** en **verwijderen** (alles gelogd), betalingshistorie en
    deelbetalingen. Rolbewuste sidebar/dashboard via `magCursusBeheer()` /
    `magCursusFinancien()`; dashboard nu ook voor Financiën
    (`rol:cursusadministratie,financien,beheerder`), beheer blijft
    Cursusadministratie. Betaalstatus **alleen-lezen** op het cursistdossier.
    10 tests (`CursusbetalingTest`); 297 groen.
  - [x] **Fase D — Cursusdirecteuren (toegang per cursus).** De rol
    **Cursusadministratie** is cursusgebonden (need-to-know, zoals Directie per
    opleiding): een cursusdirecteur ziet/beheert uitsluitend de cursussen waarvan
    hij `directeur_id` is, plus de cursisten/inschrijvingen daarop. Sleutel is de
    bestaande FK `cursussen.directeur_id` (geen pivot). Scopes
    `Cursus::/Cursist::scopeZichtbaarVoor()` + instance-guards; toegepast in alle
    admin-controllers (dashboard, beheer, cursisten, inschrijven). Cursus
    **aanmaken/verwijderen en directeur toewijzen = alleen Beheerder** (geen
    rechtenescalatie). **Boekhouding** (Financiën) blijft alle cursussen zien.
    **Schoolbestuur** kreeg de module met dashboard/statistieken en cursistinzage
    (alleen-lezen; mutatieknoppen hangen aan `magCursusBeheer()`). Seed (definitief):
    Arabisch → Hafsa; Hifz + Ijaaza → Omar (twee directeuren); guarded datamigraties
    voor de bestaande DB. 15 tests (`CursusdirecteurTest`); 312 groen.
  - [x] **Fase E — Rapportage & dashboards.** Cursusrapportage (`cursussen.rapport`):
    per cursus de inschrijvingen (per status) en cursusgelden (verschuldigd/betaald/
    openstaand + betaalgraad), totalen, grafieken (inschrijvingen/openstaand per
    cursus, betaalmethode-donut) en een **CSV-export** op cursistniveau (gelogd).
    Aggregatie in `App\Support\Cursusrapport` (niet-geannuleerde inschrijvingen;
    alleen 'Betaald' telt). Gescoped: directeur = eigen cursus(sen); Financiën,
    Beheer en Bestuur = alle cursussen. Het cursusdashboard toont nu de betaalgraad
    en het openstaande cursusgeld; de Bestuurspagina linkt door. 8 tests
    (`CursusrapportTest`); 327 groen. **Module Cursussen Administratie afgerond.**
- [x] **Module Relatiebeheer & Stagebeheer** (opleidingoverstijgend, opdrachtgever
  2026-07-11: "Voor een pabo-relatiebeheer module.txt" + de aanvulling dat de
  module óók voor Bachelor Islamitische Theologie en Master IGV is). Volwaardige
  onderwijs-CRM voor stagescholen/werkveldrelaties, contactpersonen,
  contactmomenten, stageplaatsen/plaatsingen, documenten, overeenkomsten, taken,
  agenda en rapportage. **Ontwerp:** `docs/MODULE-RELATIEBEHEER.md` (gefaseerd
  A–H, internetonderzoek onderwijs-CRM/Samen Opleiden/AVG verwerkt). Beslissingen:
  twee nieuwe rollen (relatiebeheerder + stagecoördinator), stagebeoordeling
  voldoende/onvoldoende, terminologie én organisatietypes per opleiding
  configureerbaar. AVG-grens: geen leerling-/cliëntgegevens — de stageschool blijft
  verwerkingsverantwoordelijke.
  - [x] **Fase A — Fundament & organisaties.** De platform-placeholder `stage` is
    hernoemd/uitgebouwd tot de module `relatiebeheer` (guarded migratie) en op
    actief gezet. Twee rollen `Relatiebeheerder` + `Stagecoordinator` (enum + ALTER).
    Tabellen `organisatie_types` (opzoektabel, per opleiding via `opleiding_id`,
    beheerd in Opzoektabellen), `organisaties` (leesbaar `relatienummer` R+jaar+volgnr
    via `RelatienummerGenerator`) en pivot `organisatie_opleidingen`. Modellen
    `Organisatie`/`OrganisatieType` met `scopeZichtbaarVoor` (opleidinggebonden,
    hergebruikt `directie_opleidingen`). `OrganisatieController` (CRUD + filterbalk
    op type/opleiding/status + inactiveren i.p.v. verwijderen), routes onder prefix
    `relatiebeheer` (beheer: relatiebeheerder/stagecoördinator/beheer; inzage: +
    directie/bestuur), eigen sidebar-tak, 360°-relatiekaart (basis; overige panelen
    volgen fase B–G). Aanmaken/wijzigen gelogd; opleidinggebonden gebruiker kan
    alleen de eigen opleiding(en) koppelen (server-side). Synthetische seed
    (`OrganisatieSeeder`: 9 types, 6 organisaties) + twee accounts; guarded
    datamigratie voor de draaiende DB. 10 tests (`RelatiebeheerModuleTest`); 361 groen.
  - [x] **Fase B — Contactpersonen & 360°-relatiekaart.** Tabel `contactpersonen`
    (FK organisatie, cascade) + model `Contactpersoon`; relatie op `Organisatie`.
    `ContactpersoonController` (CRUD genest onder de organisatie; edit/update/status
    op de contactpersoon), autorisatie volgt de organisatie (`beheerbaarVoor`).
    De relatiekaart (`relaties.show`) toont nu een **contactpersonen-paneel** met
    toevoegen/bewerken/inactiveren (voorkeurskanaal e-mail/telefoon/Teams);
    inactiveren i.p.v. verwijderen (historie/AVG), mutaties gelogd. Synthetische
    contactpersonen in de seeder + guarded datamigratie voor de draaiende DB.
    5 tests (`ContactpersoonTest`); 366 groen.
  - [x] **Fase C — Contactmomenten, notities & tijdlijn.** Tabellen
    `contactmoment_types` (opzoektabel, via Opzoektabellen te beheren),
    `contactmomenten` (type/contactpersoon/medewerker/datum/onderwerp/samenvatting/
    vervolgdatum) en `relatie_notities` (categorie/tags/tekst). Contactmomenten
    registreren/corrigeren (geen verwijderen — historie), gelogd; notities
    toevoegen/verwijderen (werkinformatie, niet gelogd). Nieuw paneel
    **Historie/tijdlijn** op de relatiekaart: afgeleid uit contactmomenten +
    notities + audit-log (`Relatietijdlijn`), chronologisch. `contactpersoon_id`
    op een contactmoment moet bij dezelfde organisatie horen (server-side).
    Synthetische seed + guarded datamigratie. 6 tests (`ContactmomentTest`); 372 groen.
  - [x] **Fase D — Stagebeheer (stageplaatsen + plaatsingen).** Enum `Stagestatus`
    (aangevraagd/lopend/afgerond/afgebroken). Tabellen `stageplaatsen` (aanbod per
    opleiding: aantal/max/werkdagen/eisen; **bezetting afgeleid**) en `stages`
    (leesbaar `stagenummer`; student ↔ organisatie/stageplaats, **stagebegeleider**
    = docent-user, **werkplekbegeleider** = contactpersoon; status + beoordeling).
    Stageplaatsen beheren op de relatiekaart; studenten plaatsen via "Student
    plaatsen"; module-breed **Stages-overzicht** met filters. **Beoordeling
    voldoende/onvoldoende** (gevoelig — gelogd als `stage_beoordeling`). Muteren =
    stagecoördinator (eigen opleiding) + Beheer; Directie/Bestuur zien mee.
    Server-side afgedwongen: opleiding hoort bij de organisatie, student heeft een
    actieve inschrijving in die opleiding, stageplaats/werkplekbegeleider horen bij
    dezelfde organisatie. Synthetische seed + guarded datamigratie.
    7 tests (`StageTest`); 379 groen.
  - [x] **Fase E — Taken & agenda.** Tabellen `relatie_taken` (eigen tabel naar
    Outlook/Graph `todoTask`; hergebruik enums `TaakStatus`/`TaakPrioriteit`; 'te
    laat' afgeleid) en `agenda_afspraken` (enum `AfspraakType`:
    schoolbezoek/stagebezoek/evaluatie/overleg/open dag). Taken-paneel (inline
    toevoegen + afvinken/heropenen) en Agenda-paneel op de relatiekaart, plus een
    module-brede **planning** (`agenda`) met aankomende afspraken + open taken.
    Muteren = wie de organisatie beheert (relatiebeheerder/stagecoördinator +
    Beheer); Directie/Bestuur zien mee. Geen audit-logging (werkverdeling).
    Synthetische seed + guarded datamigratie. 7 tests (`TakenAgendaTest`); 386 groen.
    (`phpunit.xml`: `memory_limit=1024M` zodat de volledige suite via `artisan
    test` draait.)
  - [x] **Fase F — Documenten, overeenkomsten & ondertekening.** Tabellen
    `relatie_documenten` (private schijf, **versiebeheer** via `vorige_versie_id`;
    inzage/upload/verwijdering gelogd) en `overeenkomsten` (type/status als enums,
    start/verloopdatum). Panelen Documenten (inline upload + nieuwe versie) en
    Overeenkomsten op de relatiekaart. **Ondertekening hergebruikt de bestaande
    module** (`Documentondertekening::ondertekenUpload` → SHA-256 + verificatiecode
    + waarmerk; status wordt Getekend). Signalering **‘contracten die verlopen’**
    (≤ 60 dagen of verstreken) op de planning en als badge op de kaart. Downloads
    ook voor Directie/Bestuur (gelogd). Synthetische seed + guarded datamigratie.
    6 tests (`DocumentOvereenkomstTest`); 392 groen.
  - [x] **Fase G — Dashboards & rapportages.** Aggregatie `App\Support\Relatierapport`
    (opleidinggebonden gescoped via `?array $oplIds`). Module opent nu op een
    **Overzicht/dashboard** met kerncijfers (organisaties, contactpersonen,
    stageplaatsen + **bezettingsgraad**, lopende/te-beoordelen stages, open taken,
    aankomende bezoeken, aflopende contracten, nieuwe documenten, % voldoende) +
    grafieken (stages per status, organisaties per type) + lijst 'te beoordelen
    stages'. **Rapportage** per organisatie met **CSV-export** (gelogd). Startroute
    verzet naar `relatiebeheer.dashboard`; organisatielijst naar `/organisaties`
    (routenaam `relaties` behouden). Sidebar: Overzicht + Rapportage. Hergebruik van
    de chart-partials. 5 tests (`RelatieDashboardTest`); 397 groen.
  - [x] **Fase H — Slimme functies & integraties.** Intranet-veilig: (1) **globaal
    zoeken** over organisaties, contactpersonen en stages (opleidinggebonden
    gescoped); (2) **iCal-export** (.ics) van de aankomende afspraken — te importeren
    in Outlook/Google/Apple (download, geen live sync); (3) **actiepunt → taak**: van
    een contactmoment met vervolgdatum een opvolgtaak maken. **Bewust buiten scope:**
    tweewegs Outlook/Graph-sync en e-mail-koppeling (externe provider, past niet in
    intranet-only). 5 tests (`RelatieSlimmeFunctiesTest`); 402 groen. **Module
    Relatiebeheer & Stage (Fasen A–H) afgerond.**
- [~] **Module HR / Personeelszaken** (opdrachtgever 2026-07-11:
  "hr presoneelszaken promt.txt"). Personeelsadministratie voor de hogeschool.
  **Ontwerp:** `docs/MODULE-HR-PERSONEELSZAKEN.md` (architectuur, datamodel, rollen,
  schermen, endpoints, MVP; gemapt op de bestaande Laravel/MySQL/Entra-stack — niet
  de generieke React/Node-suggestie uit de prompt; NL, EN-i18n later). Beslissingen:
  voltijdsnorm **40 uur** (FTE afgeleid), BSN **veld klaar/standaard uit** (config,
  akkoord FG), verlof door **manager, HR als terugval**. MVP = Fasen A + B.
  - [x] **Fase A — Fundament & medewerkersregistratie.** Module `hr` geactiveerd;
    rollen **HR-medewerker** + **Manager** (enum + ALTER). Tabellen `functies` en
    `afdelingen` (opzoektabellen; afdelingen met teamboom + manager), `medewerkers`
    (personeelsmaster, leesbaar personeelsnummer, BSN versleuteld/gated, self-service
    `user_id`), `dienstverbanden` (contracthistorie; **FTE = uren ÷ 40 afgeleid**) en
    `hr_documenten` (private schijf, gelogd). Medewerkers-CRUD + kaart (dienstverband-
    en documentpaneel) + HR-dashboard (kerncijfers, FTE-totaal, aflopende contracten).
    **Team-scoping:** de Manager ziet uitsluitend het eigen team; HR/Beheer/Bestuur
    iedereen. Mutaties gelogd. Synthetische `HrSeeder` + guarded datamigratie.
    8 tests (`HrModuleTest`); 410 groen.
  - [x] **Fase B — Verlof & verzuim.** Enums `Verloftype`/`Verlofstatus`. Tabellen
    `verlofsaldi` (recht per medewerker/jaar/type), `verlofaanvragen` en
    `ziekmeldingen`. **Self-service** verlofaanvraag (elke medewerker met gekoppeld
    account) → **goedkeuring door de manager** (HR als terugval, nooit de eigen
    aanvraag) → registratie; **saldo** per type afgeleid (recht − opgenomen via
    `Verlofoverzicht`). **Verzuim**: ziek-/herstelmelding die de medewerkerstatus
    op ziek/actief zet. Panelen Verlof/Verzuim op de medewerkerkaart + overzichten
    (Verlof, Verzuim) + zelfservice ‘Mijn verlof’; dashboard toont openstaande
    aanvragen. Beoordelen/ziekmelding gelogd. Synthetische seed + guarded
    datamigratie. 8 tests (`HrVerlofTest`); 418 groen. **MVP (A + B) compleet.**
  - [x] **Fase C — Gesprekken & performance.** Enums `Gesprekstype`
    (beoordeling/functionering/exit) en `Gespreksstatus`. Tabellen `gesprekken`,
    `gespreksdoelen` (KPI's) en `competentiescores`. HR/Manager plant een gesprek
    op de medewerkerkaart en legt op het gespreksscherm de samenvatting, feedback,
    **doelen** (open/behaald/niet behaald) en **competentiebeoordelingen**
    (onvoldoende..uitstekend) vast; historie per medewerker + module-overzicht +
    dashboardsignalering (aankomende gesprekken). Team-scoping (manager = eigen
    team via `medewerker->zichtbaarVoor`); mutaties gelogd. Synthetische seed +
    guarded datamigratie. 6 tests (`HrGesprekTest`); 424 groen.
  - [x] **Fase D — Organisatiestructuur & rapportages.** Aggregatie `App\Support\HrRapport`
    (teamgebonden gescoped via een optionele `?array $ids`). **Rapportage**:
    kerncijfers (medewerkers, totaal/gemiddeld FTE, actueel **verzuimpercentage**,
    ziektedagen) + overzicht **per afdeling** (aantal/FTE/verzuim%) met grafieken en
    **CSV-export** (per medewerker, gelogd). **Organisatiestructuur**: recursieve
    afdelingenboom (afdeling → team) met manager en aantal medewerkers. Manager ziet
    het eigen team, HR/Beheer/Bestuur alles. Geen nieuwe tabellen (hergebruik
    afdelingen/functies uit Fase A); seed kreeg een sub-afdeling PABO-team + guarded
    datamigratie. 6 tests (`HrRapportTest`); 430 groen.
  - [x] **Fase E — Onboarding/offboarding.** Enum `ChecklistSoort` met een
    `sjabloon()` per soort; tabel `hr_checklisttaken`. Op de medewerkerkaart start
    HR/Manager een onboarding-/offboarding-checklist (sjabloontaken worden
    aangemaakt, idempotent), vinkt taken af (met datum + wie) en voegt eigen taken
    toe; voortgang per checklist. Team-scoping (manager = eigen team); starten
    gelogd. Synthetische seed (demo-onboarding) + guarded datamigratie.
    5 tests (`HrChecklistTest`); 435 groen.
  - [x] **Rolsamenvoeging HR-medewerker + Manager (opdrachtgever 2026-07-11).**
    Bij IUASR zijn de HR-medewerker en de leidinggevende dezelfde persoon; de twee
    rollen zijn samengevoegd tot één rol `Rol::Hrmedewerker`. Enum-case `Manager`
    verwijderd (+ alle exhaustive matches, `magHrInzien`, `isHrTeamBeperkt()→false`,
    `magVerlofBeoordelen`), `Verlofaanvraag::beoordeelbaarVoor` vereenvoudigd,
    routemiddleware/sidebar zonder `manager`, datamigratie `merge_manager_in_hrmedewerker`
    (`manager`→`hrmedewerker` + enum-drop). Het scopingsmechanisme blijft aanwezig
    (nu no-op). HR-scopingtests omgezet naar "gecombineerde rol ziet iedereen".
  - [x] **Fase F — Self-service "Mijn HR" & iCal-agenda.** Eigen 360°-dossier
    (`hr.mijn`) voor elke gekoppelde medewerker (gegevens, verlofsaldo, aanvragen,
    gesprekken, documenten, checklists), alleen-lezen; autorisatie via
    `medewerkers.user_id` (geen aparte rol). iCal-export (`hr.mijn.agenda`,
    `App\Support\Icsagenda`) van geplande gesprekken + goedgekeurd verlof — een
    zelfstandig `.ics`-bestand (geen live Outlook/Teams; intranet-only). Eigen
    documentdownload met eigendomscheck + INZAGE-log. Zelfservice-menu (Mijn HR +
    Mijn verlof) voor elke medewerker, ook buiten de HR-module. HR-dashboard
    uitgebreid met actuele ziekmeldingen. 5 tests (`HrMijnTest`).
  - [x] **Organisatiestructuur-beheer door HR (opdrachtgever 2026-07-11).** De
    HR-medewerker beheert afdelingen/teams én functies rechtstreeks op
    `hr.organisatie` (voorheen alleen Beheer via Opzoektabellen).
    `Hr\OrganisatiebeheerController` (afdeling/functie store/update/destroy),
    inline-bewerkbare rijen (HTML5 `form`-attribuut), verwijder-vergrendeling
    (medewerkers/onderliggende teams → 422), mutaties gelogd. Alleen bij
    `magHrBeheer`; Bestuur alleen-lezen. 8 tests (`HrOrganisatiebeheerTest`).
  - [x] **Fase G — Slimme functies.** Geen nieuwe tabellen. (1) **Globaal zoeken**
    (`hr.zoeken`, `Hr\ZoekController`) over medewerkers (naam/personeelsnummer/
    e-mail) en afdelingen, team-gescoped via `Medewerker::zichtbaarVoor`. (2)
    **Signaleringen** (`hr.signaleringen`, `Hr\SignaleringController`): aflopende
    contracten (`sis.hr.contract_signaal_dagen`, default 60) + verzuimsignalering.
    (3) **Verzuimsignalering** (`App\Support\Verzuimsignalering`): `langdurig()`
    leidt per open ziekmelding de **Wet Verbetering Poortwachter**-mijlpalen af uit
    de eerste ziektedag (probleemanalyse wk 6, plan van aanpak wk 8, UWV-ziekmelding
    wk 42, eerstejaarsevaluatie wk 52, WIA-aanvraag wk 93) met status verstreken/
    binnenkort/gepland; `frequent()` signaleert ≥ 3 losse ziekmeldingen in 12 mnd.
    Mijlpalen/drempels in `config/sis.php` (`sis.hr.poortwachter`). Geen afgehandelde
    status per mijlpaal (informatief; re-integratie via bedrijfsarts). Inzagegroep
    `rol:hrmedewerker,beheerder,bestuur`; sidebar Zoeken + Signaleringen; HR-dashboard
    kreeg een Signaleringen-knop. Synthetische seed (Mehmet = frequent verzuim).
    **Buiten scope:** live Teams/Outlook, e-mail, NL/EN-i18n. 8 tests
    (`HrSlimmeFunctiesTest`); 458 groen. **Module HR / Personeelszaken afgerond.**
  - [x] **Interne notities per medewerker (opdrachtgever 2026-07-11).** Hetzelfde
    notitieblok als bij Studentenzaken, nu op de medewerkerkaart: een doorlopend
    logboek van **contactmomenten** (e-mails, telefoongesprekken, gespreksverslagen)
    met **datum + auteur** per notitie. Tabel `medewerker_notities` + model
    `MedewerkerNotitie`; `Medewerker::notities()` (nieuwste eerst).
    `Hr\MedewerkerController::notitieStore/notitieDestroy` (routes
    `medewerkers.notities.store/destroy` in `rol:hrmedewerker,beheerder`). HR/Beheer
    beheren, Bestuur leest mee. Werkinformatie, niet audit-gelogd (zoals de student-/
    relatie-notities); geen BSN. Synthetische seed (twee notities). 5 tests
    (`HrNotitieTest`); 463 groen.
  - [x] **Rapportage "Verzuim & verlof per medewerker" (opdrachtgever 2026-07-11).**
    De HR-rapportage toonde alleen kerncijfers + per afdeling; nu een overzicht om
    **elke medewerker te volgen op ziekteverzuim en verlof**. `HrRapport::perMedewerker`
    levert per medewerker: aantal ziekmeldingen, ziektedagen en verzuim% (kalenderdag-
    gebaseerd) + verlofrecht/opgenomen/saldo (alle typen samen) en openstaande
    aanvragen — via geaggregeerde group-by-queries (geen N+1). `HrRapportController::
    verzuimVerlof` (view `hr.verzuimverlof`) + CSV-export (gelogd); jaar- en
    afdelingsfilter, team-gescoped. KPI-tegels + tabel (kolomgroepen Verzuim | Verlof)
    met doorklik naar de medewerkerkaart. Sidebar-item + knop op de rapportagepagina.
    Geen nieuwe tabellen. 7 tests (`HrVerzuimVerlofRapportTest`); 470 groen.
- [ ] **Fase 6 — Portaalkoppeling**
  - Koppeling met publiek aanmeldportaal (gescheiden regime).
- [ ] **Fase 7 — Migratie**
  - Migratie van echte data uit oud Access-systeem, laatste fase, onder toezicht FG.
- [ ] **Fase 8 — Uitbreidingen**
  - Latere modules na oplevering kern.

---

## Nog niet begonnen / expliciet uit scope

Niet bouwen tenzij expliciet gevraagd:

- **DUO/BRON-koppeling** — blijft handmatig, apart regime.
- **Betalingsmodule** — GEBOUWD op expliciet verzoek (2026-07-07): collegegeld per
  studiejaar, betalingsregistratie door Financiële Administratie, automatische
  achterstandsbepaling en blokkades. Zie beslissingenlogboek.
- **Moodle-provisioning** — latere fase.
- **E-mailautomatisering** — latere fase.
- **Migratie van echte data** — pas Fase 7, onder toezicht FG.

---

## Openstaande parameters (TE BEVESTIGEN — niet zelf verzinnen)

Deze zijn nog niet vastgesteld. Vraag de opdrachtgever; verzin geen waarden.

- [x] **Studentnummerformaat** — BEVESTIGD 2026-07-06: jaarprefix (2) + volgnummer,
  totaal 6 tekens (voorbeeld 261234). Vastgelegd in `config/sis.php`.
- [ ] **Nummerbeleid bij heringstroom** — behoudt student oud nummer of nieuw nummer?
- [x] **Voldoende-grens (cesuur)** — BEVESTIGD 2026-07-07: **5,5** voor alle
  opleidingen (per opleiding overschrijfbaar via `opleidingen.voldoende_grens`).
- [ ] **EC-drempels per opleiding** — BESLUIT 2026-07-07: **per opleiding
  verschillend**; veld staat klaar (`opleidingen.ec_overgang_drempel`, nu null),
  Beheer vult per opleiding in via Opzoektabellen. Blokkeert alleen de latere
  leerjaar-herbeoordeling, niet de cijferinvoer.
- [x] **Aanwezigheidsnorm** — BEVESTIGD 2026-07-09: **80%** regulier, **50%** bij
  de aanwezigheidsregeling; **8 onderwijsweken per blok**, één college per week.
  Vastgelegd in `config/sis.php` (`sis.presentie`). Nog niet per opleiding
  instelbaar — vraag de opdrachtgever als dat later nodig blijkt.

---

## Beslissingenlogboek

| Datum | Beslissing |
|------------|------------|
| 2026-07-06 | Stack vastgelegd: PHP + Laravel (geen kale PHP/WordPress). |
| 2026-07-06 | Database: MySQL / InnoDB met echte foreign keys, surrogaatsleutels overal. |
| 2026-07-06 | Authenticatie via Microsoft Entra ID (SSO/OIDC); geen eigen login. |
| 2026-07-06 | Cijfers genormaliseerd (resultaatregels + toetsonderdelen + weging); nooit blok-kolommen. |
| 2026-07-06 | Git-repo geinitialiseerd, remote origin gezet; eerste push in overleg. |
| 2026-07-06 | AVG-veilige `.gitignore`: alle DB-/dumpformaten, secrets en gevoelige-data-mappen uitgesloten. |
| 2026-07-06 | Laravel 12-skelet opgezet (hand-scaffold; PHP/Composer nog niet geïnstalleerd). |
| 2026-07-06 | Datamodel in migraties: surrogaatsleutels, echte FK's, genormaliseerde resultaatregels, versleuteld BSN + audit-log. |
| 2026-07-06 | Rolscheiding server-side: `Rol`-enum, Gates (`AutorisatieServiceProvider`) en `rol`-middleware. |
| 2026-07-06 | Leidend design system: `IUASR/iuasr-sis` overgenomen naar `public/assets` en gekoppeld aan Blade-layout. |
| 2026-07-06 | Openstaande parameters bewust `null` in `config/sis.php` en op `opleidingen` (voldoende_grens, ec_overgang_drempel, studentnummerlengte) — niet zelf ingevuld. |
| 2026-07-06 | Lokale toolchain: PHP 8.3.32 + Composer 2.10 + portable MariaDB 11.4.9 (poort 3307, user-profiel, geen service/admin). |
| 2026-07-06 | Database NIET in Git: schema via migraties, data via synthetische seeders; `.env` per omgeving (lokaal↔intranet). Zie docs/ONTWIKKELOMGEVING.md. |
| 2026-07-06 | Studentnummer BEVESTIGD: jaarprefix (2) + volgnummer, totaal 6 tekens (voorbeeld 261234). |
| 2026-07-06 | Tijdelijke dev-login (alleen lokaal) om rolscheiding te bouwen/testen; vervangen door Entra ID SSO in latere fase. |
| 2026-07-06 | App-shell (header/sidebar) server-side gerenderd op basis van de ingelogde rol; `sis-shell.js` (localStorage-demo) niet meer leidend in de app. |
| 2026-07-07 | Inschrijvingstatus als enum met o.a. `geschorst`; schorsen/opheffen met één klik (omkeerbaar). |
| 2026-07-07 | Uitschrijfdatum wordt berekend als einde van de lopende maand (wettelijke regel). |
| 2026-07-07 | Verklaringen bevatten nooit cijfers of BSN; uitgifte wordt gelogd. |
| 2026-07-07 | Opzoektabellen-beheer via één generieke referentie-CRUD (registry), i.p.v. losse controllers per tabel. |
| 2026-07-07 | Cesuur (voldoende-grens) bevestigd op 5,5 voor alle opleidingen; per opleiding overschrijfbaar. |
| 2026-07-07 | EC-overgangsdrempel per opleiding (veld klaar, door Beheer in te vullen). |
| 2026-07-07 | Reguliere cijferinvoer alleen door de docent (eigen vak); examencie/directie hebben inzage. Vaststelling/correctie door examencie volgt in Fase 4 increment 2. |
| 2026-07-07 | Nieuwe rol **Financiële Administratie** (registreert betalingen; geen cijfers/BSN). |
| 2026-07-07 | Collegegeldmodule (Studentenadministratie): tarief per studiejaar, optioneel per opleiding, jaarlijks bij te werken. |
| 2026-07-07 | Betalingsachterstand automatisch bepaald (verschuldigd − betaald). Bij achterstand: waarschuwing op dossier, en blokkade van herinschrijven (studievoortgang) en verklaringen (documenten). |
| 2026-07-07 | Automatische vaktoewijzing bij (her)inschrijving: vakken van het studiejaar (leerjaar) worden gekoppeld aan de inschrijving; SZ kan per student aanpassen. |
| 2026-07-07 | Vakstructuur-beheermodule (curriculum): per opleiding × studiejaar (leerjaar) × periode (blok) vakken beheren; toegewezen vakken beschermd tegen verwijderen (historie). |
| 2026-07-07 | Vakhistorie op studentdossier onder Inschrijving & klas: studiejaar-tabs → periode-subtabs; blijft ook jaren later raadpleegbaar. |
| 2026-07-08 | **Vrijstellingen** per toegewezen vak (uitbreiding `vaktoewijzingen`). Formeel verleend door de examencommissie; **Studentenzaken registreert** het besluit op de studentpagina met VERPLICHTE besluit-referentie + datum. Rolscheiding: dit is een administratieve status, GEEN cijfer. Een vrijstelling kent automatisch de volledige vak-EC toe, zonder eindcijfer (vermelding **VR** op cijferlijst/tentamenlijst; telt mee in EC-rapport en overgangsadvies). Grondslag: vooropleiding/EVC/eerder behaald/overig. HBO-praktijk geverifieerd (OER Hogeschool Leiden/HU/HvA). |
| 2026-07-08 | **Landelijke kennistoetsen (PABO)** — bewaking zoals NT2, maar voor meerdere toetsen. PABO-studenten moeten de RWT (Reken-/Wiskundetoets, opvolger WISCAT) en de LKT-kennisbasistoetsen taal en rekenen binnen **2 jaar** (config sis.kennistoetsen.termijn_jaren) na inschrijving halen. Modellen Kennistoets (per opleiding) + Kennistoetsresultaat (student × toets, behaald_op); support Kennistoetsbewaking (deadline/status open/afgerond/verlopen). Zodra een student op de PABO inschrijft, verschijnt op het dossier automatisch de kaart 'Landelijke kennistoetsen' met status per toets + deadline; SZ registreert behaalde toetsen. SZ-dashboardtegel met openstaande/verstreken gevallen. Toetsen te beheren via de Kennistoets-tabel; HBO-praktijk geverifieerd (RWT/LKT). |
| 2026-07-08 | **Cijferlijst per opleiding + resultaten e-mailen naar studenten** (einde blok). Cijferlijst-pagina heeft naast 'per student' nu 'alle studenten per opleiding'. Vanaf die lijst kan de examencommissie de definitieve resultaten mailen: een controlescherm toont ontvangers en overgeslagenen (geen vastgestelde resultaten / geen e-mail); na bevestiging krijgt ELKE student INDIVIDUEEL de eigen ondertekende cijferlijst (transcript, alleen definitieve resultaten) als PDF-bijlage. AVG: nooit bulk-zichtbaar; in ontwikkeling MAIL_MAILER=log (geen echte mails), productie via IUASR-SMTP. Verzending gelogd. Keuze opdrachtgever: per opleiding, volledige transcript, alleen vastgesteld + ondertekend. |
| 2026-07-08 | **Vrijstelling-workflow examencommissie -> Studentenzaken** (intern, geen e-mail). De examencommissie legt op het studentdossier een vrijstellingsbesluit vast ("Naar Studentenzaken sturen"); dit verschijnt als taak op het SZ-dashboard. Studentenzaken verwerkt het met ÉÉN klik, waarna de vrijstelling automatisch op de vak-toewijzing wordt vastgelegd (volledige EC, VR) en de status terugkoppelt (openstaand/verwerkt/geannuleerd). Blijft binnen het systeem, volledig gelogd; rolscheiding: SZ maakt geen besluit, examencommissie verwerkt niet. Handmatige SZ-registratie blijft mogelijk. Keuze opdrachtgever: interne melding (geen e-mail) + één-klik-verwerken. |
| 2026-07-08 | **Twee PDF-handleidingen** (genereerbaar uit Blade + `php artisan handleidingen:genereren` → docs/handleidingen/): (1) medewerkershandleiding (gebruik van de webapp) — voor iedereen via een **Help-link linksboven** in de paginabalk; (2) technische handleiding (architectuur, back-up en **data-recovery/herstel** met exacte commando's) — voor Beheerder én Schoolbestuur. Herstelcommando `php artisan backup:uitpakken` (AES-ZIP openen dat Windows Verkenner niet kan). WERKWIJZE vastgelegd in CLAUDE.md: bij elke nieuwe functie beide handleidingen bijwerken. |
| 2026-07-08 | **Recovery-backup** (Beheerder): downloadt de volledige installatie als één met wachtwoord (AES-256) versleutelde ZIP. Inhoud: pure-PHP databasedump (structuur + data, geen mysqldump nodig), applicatiebroncode + webpagina's, `.env` INCL. `APP_KEY` (nodig om BSN/rekeningnummer te ontsleutelen) en de geüploade bestanden (documenten/ondertekende PDF's). Uitgesloten: `vendor/` (composer install), `.git/`, `IUASR/`. Wachtwoord wordt bij het maken opgegeven en nergens opgeslagen; downloaden gelogd. Manifest met herstelprocedure in het archief. |
| 2026-07-08 | **Rolgerichte statistiek-dashboards** met server-gerenderde grafieken (SVG/CSS, geen externe libs — intranet-veilig). `Statistiek`-service aggregeert uit de eigen DB: studenten per opleiding/leerjaar, instroom per studiejaar, inschrijvingsstatus, overgangsadvies (BSA), toets-slaagpercentage, cijferverdeling, cijferlijst-status, herkansingen, vrijstellingen, NT2, collegegeld (verschuldigd/betaald/openstaand/betaalgraad/achterstanden), gebruikers per rol. Per rol alleen relevante + toegestane cijfers: Directie/Bestuur strategisch (studiesucces, instroom, financiën), Examencommissie (slaag, cijferverdeling, overgang, cijferlijst-status), Financiën (betaalgraad, openstaand p/opleiding), Studentenzaken (aantallen/instroom, GEEN cijfers), Beheer (gebruikers per rol). HBO-KPI's geverifieerd (Vereniging Hogescholen: studiesucces/uitval/instroom/rendement). Rapporten-kaart 'Examen-/tentamenlijst' geactiveerd (via Cijferoverzicht). |
| 2026-07-09 | **Tentamenlijst -> presentielijst** (tijdens tentamen): printbare A4 met kolommen #, studentnr., naam en **handtekening** (aanwezigheidsbevestiging). EC/cijfers verwijderd — die privé-info mag niet zichtbaar zijn voor medestudenten. Zowel het scherm als de ondertekende PDF. Tegelijk **alle printknoppen gecontroleerd/gefixt**: `@media print` in `sis.css` verbergt nu shell/sidebar/toolbar/alerts en zet `@page`-marge. |
| 2026-07-09 | **Notities zichtbaar voor Directie en Bestuur** (alleen-lezen). Interne notities op het dossier: SZ/Beheer beheren (toevoegen/verwijderen), Directie en Schoolbestuur lezen mee. Bestuur kreeg toegang tot het studentdossier (studenten.index/show); BSN-inzage blijft voor Bestuur geweigerd. |
| 2026-07-09 | **Directie per opleiding (opleidinggebonden zichtbaarheid).** Nieuwe koppeltabel `directie_opleidingen` (user ↔ opleiding, echte FK's, cascade). Een directielid ziet uitsluitend studenten, cijfers en rapporten van de toegewezen opleiding(en); zonder toewijzing niets (need-to-know). Toegepast op studentenlijst/-dossier, cijferoverzicht, cijferlijst, EC-rapport, overgang, alumni, resultaten-mailen én de dashboardstatistieken (per-opleiding gefilterd) + KPI-tegels. Beheer wijst toe via **Gebruikers & rollen → Directie — opleidingtoewijzing**. Seed (oorspronkelijk; later herverdeeld, zie 2026-07-10): PABO-directeur (PABO), GV-directeur (PMGV+MGV), Theologie-directeur (ISLTH+cursussen). Een **dubbel ingeschreven** student is zichtbaar voor de directie van elke opleiding waarin hij/zij actief is. Helpers `User::opleidingIds()`, `Student::scopeZichtbaarVoor()`/`zichtbaarVoor()`. |
| 2026-07-09 | **Dubbele inschrijving overal zichtbaar gemaakt.** Studentenlijst: opleidingkolom toont twee regels + label 'dubbele inschrijving' bij de naam. Dashboards van Studentenzaken, Directie en Financiële Administratie tonen een lijst 'Studenten met een dubbele inschrijving' (met beide opleidingen). Studentpagina toonde dit al (kop met '+' en pill). Financiën-dossier toont per inschrijving al de opleiding. |
| 2026-07-09 | **50%-aanwezigheidsregeling.** Vastgelegd als vinkje "50% Aanwezigheidsregeling" op de studentpagina (tabblad Inschrijving & klas). Keuze opdrachtgever: ALLEEN een vinkje — geen besluitreferentie in de UI; de toestemming van de directie loopt buiten het systeem, de mutatie wordt wel gelogd (wie/wanneer/welke inschrijving). Reikwijdte: **per inschrijving** (= per opleiding én per studiejaar), dus bij herinschrijven of een tweede opleiding bewust opnieuw toe te kennen. Zetten: Studentenzaken/Beheer. Zien: SZ, Docent, Examencommissie, Directie, Bestuur, Beheer (niet Financiën). Dashboardvenster met de studenten, opleiding en studiejaar; docent ziet alleen studenten uit eigen vakken, directie alleen eigen opleiding(en). |
| 2026-07-10 | **Alumni-rapport ook voor het Schoolbestuur.** Route `rapporten.alumni` uitgebreid van `studentenzaken,directie` naar `studentenzaken,directie,bestuur`, met menu-item **Rapporten -> Alumni** in de bestuurs-sidebar. Verantwoord: het rapport bevat uitsluitend naam, contactgegevens en opleiding van afgestudeerden — geen cijfers en geen BSN, en Bestuur heeft die gegevens al via het studentdossier. Bestuur is niet opleidinggebonden en ziet dus alle alumni (Directie alleen de eigen opleiding(en)). Tevens gefixt: de kruimelpad- en Terug-link van het alumni-scherm wezen voor Bestuur naar de SZ-route `rapporten`, wat een 403 zou geven; die verwijst nu naar het dashboard. |
| 2026-07-10 | **Collegegeldtarieven 2026-2027 vastgesteld** (opdrachtgever): Bachelor Islamitische Theologie (ISLTH) EUR 3.500, Pre-Master GV (PMGV) EUR 3.500, Master GV (MGV, "Master IGV") EUR 4.000, PABO EUR 3.500; elk 5 termijnen. Gezet via migraties (bestaande DB, guarded insert) en `ReferentieSeeder` (verse migrate:fresh). ISLTH is in een aparte, latere migratie toegevoegd. De cursussen Arabisch en Koran & Hifz hebben (net als voorheen) geen tarief.
| 2026-07-10 | **Betalingsafspraak heft de blokkade op.** Bij een achterstand kon Studentenzaken geen verklaring afgeven en de student niet herinschrijven. De **Financiele Administratie** kan nu een betalingsafspraak vastleggen (verplichte einddatum in de toekomst + reden); zolang die loopt vervallen **beide** blokkades. Keuzes opdrachtgever: (1) alleen Financien/Beheer mag de afspraak vastleggen of intrekken -- Studentenzaken kan haar eigen blokkade dus niet opheffen; (2) de afspraak heft verklaringen EN herinschrijven op; (3) geldig tot een einddatum, daarna keert de blokkade automatisch terug; (4) de waarschuwing blijft op het dossier staan, met de afspraak erbij. Cruciaal onderscheid in `Collegegeldstatus::voor()`: **`achterstand`** (de schuld, blijft true) versus **`geblokkeerd`** (het gevolg, false tijdens een afspraak). Blokkeer altijd op `geblokkeerd`. `Betalingsafspraak::isLopend()` is afgeleid uit einddatum + intrekmoment, nooit een opgeslagen status. Een nieuwe afspraak trekt de lopende automatisch in. Alles gelogd. Venster `Lopende betalingsafspraken` op het Financien-dashboard. |
| 2026-07-10 | **BELEIDSWIJZIGING: collegegeld PER OPLEIDING, met korting.** Vervangt het besluit van 2026-07-07 ("per studiejaar eenmaal") en daarmee ook de maatgevende-inschrijving-oplossing van eerder vandaag. Elke inschrijving heeft nu een eigen jaartarief, een eigen termijnschema en eigen betalingen; de bedragen tellen op. Keuzes opdrachtgever: (1) korting als **percentage per inschrijving** (`inschrijvingen.korting_percentage` + verplichte `korting_reden`), (2) **Studentenzaken kiest expliciet** welke inschrijving korting krijgt — het systeem leidt nooit af welke opleiding "de tweede" is, (3) de Financiele Administratie mag betalingen **wijzigen en verwijderen**, beide gelogd met oude en nieuwe waarden, (4) een achterstand bij **een van beide** opleidingen blokkeert herinschrijven en verklaringen. Bij 100%% korting ontstaan geen facturen. Betalingen worden nooit tussen opleidingen verrekend. Verwijderd: `maatgevende()`, `isMaatgevend()`, `verrekendBij()`, `inschrijvingenVanStudiejaar()`, `verschuldigdTotaal()`. Nieuw: `Collegegeldstatus::jaarbedrag()` (tarief na korting), `KortingController`, `BetalingController::bijwerken()/verwijderen()`. |
| 2026-07-10 | **Pre-Master GV telt 50 EC, niet 60.** BEVESTIGD door de opdrachtgever en in overeenstemming met het curriculum (12 vakken, samen exact 50 EC). `opleidingen.ec_totaal` voor PMGV van 60 naar 50, zowel in `ReferentieSeeder` als via een datamigratie (`premaster_ec_totaal_naar_50`, alleen als de waarde nog 60 is, zodat een handmatige correctie via Opzoektabellen niet wordt overschreven). `ec_totaal` is de noemer op de cijferlijst/transcript en in de voortgangsbalk van het EC-rapport; met 60 leek een afgeronde pre-master onvoltooid (50/60). Nieuwe test bewaakt dat het nominale totaal per opleiding klopt met het curriculum (PMGV 50, MGV 120, ISLTH 220 verplicht + 20 keuzeruimte = 240). |
| 2026-07-10 | **Collegegeld bij dubbele inschrijving hersteld (rekenfout).** Het termijnschema hing aan een INSCHRIJVING, terwijl collegegeld per STUDIEJAAR eenmaal verschuldigd is. Gevolg: een betaling die op de tweede inschrijving werd geboekt telde wel mee in `betaald`, maar niet in de termijnen van de maatgevende inschrijving — een student die het volledige jaarbedrag had voldaan hield achterstallig EUR 4.000 en werd geblokkeerd voor herinschrijven en verklaringen. Bovendien toonde het Financien-scherm twee volledige termijntabellen met boek-knoppen zonder aan te geven welke de echte was. Opgelost: `Collegegeldtermijnen::maatgevende()` kiest per (student, periode) de inschrijving met het hoogste verschuldigde bedrag (tie-break laagste id); alleen die levert termijnen op, en betalingen worden over ALLE inschrijvingen van dat studiejaar verrekend. `BetalingController` boekt nieuwe betalingen (ook uit de CSV-import) automatisch op de maatgevende. Keuzes opdrachtgever: twee tabellen waarvan de tweede gemarkeerd als `Geen collegegeld verschuldigd`; hoogste jaartarief is maatgevend; de betaalregeling van de maatgevende geldt. |
| 2026-07-10 | **Takenlijst voor Studentenzaken.** Model ontleend aan Outlook Taken / Microsoft Graph `todoTask` (titel, begindatum, vervaldatum, status, prioriteit), maar met **drie** statussen (open/bezig/afgerond) i.p.v. vijf — 'waitingOnOthers' en 'deferred' zijn geschrapt. Keuzes opdrachtgever: (1) **gedeelde** afdelingslijst met toewijzing (niet-toegewezen taken zijn vrij op te pakken); (2) taak **optioneel koppelbaar aan een student**, verschijnt dan op het dossier; (3) drie statussen; (4) herinnering via een **dashboardvenster** 'Mijn taken' (geen e-mail). 'Te laat' wordt AFGELEID uit vervaldatum + status en is bewust geen kolom, zodat een afgeronde taak nooit als te laat kan blijven staan. Taken zonder vervaldatum vallen buiten de signalering. Toegang alleen Studentenzaken + Beheer; geen audit-logging (werkverdeling, geen gevoelig gegeven). |
| 2026-07-10 | **Echt curriculum ingeladen (91 vakken).** Bron: 'vakkenlijst update.xlsx' → `database/data/curriculum.csv` + `CurriculumSeeder`. Keuzes opdrachtgever: (1) `vakken.ec` wordt **decimal(4,1)** want 28 vakken hebben 2,5 EC — afronden zou de jaartotalen 11 EC laten afwijken; (2) vakcode is **uniek per opleiding**, want elf codes bestaan in zowel ISLTH als PMGV (aparte vakken, eigen cijferlijst/docent/presentielijst); (3) de keuzeruimte krijgt `vakken.keuzevak` en wordt **niet automatisch toegewezen** — anders telt bachelor jaar 4 95 EC i.p.v. 40 verplicht; (4) de 9 synthetische vakken (ISLTH-*) zijn **definitief verwijderd** met hun 90 toewijzingen, 15 cijfers, 94 presenties, 5 cijferlijsten en 3 vrijstellingsbesluiten, omdat zij naast het echte curriculum meetelden (jaar 1 werd 94 i.p.v. 60 EC) en automatisch aan elke ISLTH-student werden toegewezen. Bij tegenstrijdigheid in de bronlijst is de tekstkolom 'Blok' leidend (B-SC07 → 4, B-AR06-15 → 2); `B-FQ02-B-FQ03` blijft één vak. Vakken zonder blok lopen het hele studiejaar (stage, scriptie). De synthetische vakken zijn verhuisd naar `SynthetischVakSeeder` (uitsluitend testfixture, niet in `DatabaseSeeder`). PABO volgt later. |
| 2026-07-10 | **Collegegeld in termijnen.** Facturering elke twee maanden: september, november, januari, maart en mei. Keuzes opdrachtgever: (1) termijnbedrag = **jaarbedrag ÷ 5**, afrondingsrestje op de laatste termijn; (2) **achterstand = onbetaalde VERVALLEN termijn** — een nog niet vervallen termijn is geen achterstand (vervangt de oude maand-pro-rata als achterstandsmaatstaf en stuurt de blokkades op herinschrijven/verklaringen); (3) bij tussentijdse uitschrijving worden de termijnen **pro rata herrekend**: termijnen ná de uitschrijfdatum vervallen, de laatste geldende termijn wordt bijgesteld; (4) de **betaalregeling** (vijf termijnen óf één factuur voor het volledige jaarbedrag) wordt door **Studentenzaken** op het dossier vastgelegd, per inschrijving en dus per studiejaar, en gelogd. Geen facturentabel: het schema is afgeleid uit jaartarief + regeling + inschrijvingsduur (`Collegegeldtermijnen`), zodat het nooit veroudert. `betalingen.termijn` (nullable) koppelt een betaling aan een termijn; leeg = FIFO naar de oudste openstaande termijn. Financiën boekt met één klik per termijn; CSV-import kreeg een optionele termijnkolom en herkent kolommen op naam (oude bestanden blijven werken). De kolom `inschrijvingen.betaalwijze` is vervallen (mengde regeling en betaalwijze) en blijft alleen voor historie. |
| 2026-07-11 | **Bestuur houdt altijd het eigen menu op modulerapporten (opdrachtgever).** Klachtenpunt: opende het Schoolbestuur een modulerapport (bv. het HR-rapport), dan schakelde de sidebar naar het module-menu; dat menu bevat beheerlinks (HR: verlof/verzuim/gesprekken) waarvoor Bestuur geen rechten heeft → 403 bij aanklikken, en het gevoel "onbedoeld in een andere module te belanden". Oplossing in `partials/sidebar`: voor `$rol === Rol::Bestuur->value` wordt **altijd** het Bestuur-menu gekozen (module-detectie `$inHrmodule`/`$inCursusmodule`/`$inRelatiemodule` overgeslagen). Het Bestuur-menu kreeg een groep **Rapportages** met directe links naar alle (alleen-lezen) rapporten die Bestuur mag zien: Alumni, Aanwezigheid, Cursusrapportage, Relatiebeheer, HR-rapportage en HR verzuim & verlof (alle routegroepen bevatten `bestuur`). Op de Bestuurspagina is de knop "HR verzuim & verlof" toegevoegd. Geen route-/rechtenwijziging: alle routes blijven bereikbaar, alleen de sidebar blijft consistent Bestuur. HR-medewerker/Beheerder houden het module-menu. 3 tests toegevoegd aan `BestuurPaginaTest`; 473 groen. |
| 2026-07-11 | **Module HR / Personeelszaken — rapportage "Verzuim & verlof per medewerker" (opdrachtgever).** De HR-rapportage (kerncijfers + per afdeling) was onvoldoende om medewerkers individueel te volgen op ziekte en verlof. Nieuw overzicht met **één regel per medewerker**: verzuim (aantal ziekmeldingen, ziektedagen, verzuim% kalenderdag-gebaseerd = ziektedagen ÷ verstreken dagen in het jaar) én verlof in uren (recht, opgenomen = alleen goedgekeurd, saldo, openstaande aanvragen). Aggregatie `HrRapport::perMedewerker(?array $ids, ?int $jaar)` met geaggregeerde group-by-queries (geen N+1). Controller `Hr\HrRapportController::verzuimVerlof`/`verzuimVerlofExport` (CSV met BOM, gelogd), helper `verzuimScope()` (team-scope + optioneel afdelingsfilter), jaarkeuze (huidig t/m 3 terug). View `hr.verzuimverlof`: KPI-tegels + tabel met kolomgroepen Verzuim | Verlof, rij-link naar de medewerkerkaart, negatief saldo rood. Routes in de inzagegroep `rol:hrmedewerker,beheerder,bestuur`; sidebar-item + knop op de rapportagepagina. Keuzes: verzuim% bewust kalenderdag-gebaseerd (geen weging met deelverzuimpercentage, consistent met `Ziekmelding::dagen()`); verlof telt alle typen samen. Geen nieuwe tabellen. 7 tests (`HrVerzuimVerlofRapportTest`); 470 groen. |
| 2026-07-11 | **Module HR / Personeelszaken — interne notities per medewerker (opdrachtgever).** Op verzoek hetzelfde notitieblok als bij Studentenzaken, nu op de medewerkerkaart, bedoeld als **doorlopend logboek van contactmomenten** (e-mails, telefoongesprekken, gespreksverslagen). Elke notitie krijgt automatisch **datum + auteur**; nieuwste bovenaan. Tabel `medewerker_notities` (`medewerker_id` cascade, `gebruiker_id` nullOnDelete, `tekst`, timestamps) + model `MedewerkerNotitie`; `Medewerker::notities()` (`latest()`). Controller-acties `Hr\MedewerkerController::notitieStore/notitieDestroy` (autorisatie `beheerbaarVoor` = `magHrBeheer`; destroy checkt eigenaarschap), routes `medewerkers.notities.store/destroy` in de HR-beheergroep `rol:hrmedewerker,beheerder`. Notitieblok onderaan `hr/medewerker-show` (hergebruik `iuasr-dash-note*`-klassen): **HR & Beheer beheren, Bestuur leest mee**. Keuze/afspraak (consistent met de student- en relatie-notities): **werkinformatie, niet audit-gelogd**, geen BSN of bijzondere persoonsgegevens. Synthetische seed (twee notities bij Sophie Willemsen); nieuwe migratie (geen guarded datamigratie nodig — verse tabel). 5 tests (`HrNotitieTest`); 463 groen. |
| 2026-07-11 | **Module HR / Personeelszaken — Fase G (slimme functies).** Afsluitende, intranet-veilige fase; geen nieuwe tabellen. (1) **Globaal zoeken** (`hr.zoeken`, `Hr\ZoekController`) over medewerkers en afdelingen, team-gescoped. (2) **Signaleringen** (`hr.signaleringen`, `Hr\SignaleringController`): aflopende contracten (`sis.hr.contract_signaal_dagen`, default 60) + verzuim. (3) **Verzuimsignalering** (`App\Support\Verzuimsignalering`) volgens de **Wet Verbetering Poortwachter** (keuze opdrachtgever: volledige wettelijke mijlpalen): `langdurig()` leidt per open ziekmelding de mijlpaaldata af uit de eerste ziektedag (probleemanalyse wk 6, plan van aanpak wk 8, UWV-ziekmelding wk 42, eerstejaarsevaluatie wk 52, WIA-aanvraag wk 93) met status verstreken/binnenkort/gepland; `frequent()` signaleert ≥ 3 losse ziekmeldingen in 12 mnd. Mijlpalen/drempels configureerbaar in `config/sis.php` (`sis.hr.poortwachter`); het systeem houdt bewust **geen** afgehandelde status per mijlpaal bij (informatief hulpmiddel — de formele re-integratie loopt via de bedrijfsarts/arbodienst). Inzagegroep `rol:hrmedewerker,beheerder,bestuur`; sidebar Zoeken + Signaleringen (nieuwe icoonsleutels `search`/`alert`); HR-dashboard kreeg een Signaleringen-knop. Synthetische seed: Mehmet (P260004) drie herstelde ziekmeldingen (frequent verzuim); geen migratie nodig. **Bewust buiten scope:** live Teams/Outlook-integratie, e-mail (externe provider; intranet-only) en NL/EN-i18n. 8 tests (`HrSlimmeFunctiesTest`); 458 groen. **Module HR / Personeelszaken (Fasen A–G) afgerond.** |
| 2026-07-11 | **Module HR / Personeelszaken — Organisatiestructuur-beheer door HR (opdrachtgever).** De HR-referentiedata (afdelingen/teams en functies) is nu rechtstreeks te muteren op `hr.organisatie` door de HR-medewerker (`magHrBeheer`), voorheen alleen door de Beheerder via Opzoektabellen. `Hr\OrganisatiebeheerController` (afdeling/functie store/update/destroy); validatie spiegelt `ReferentieController` (code uniek via `Rule::unique()->ignore()`, categorie ∈ `Functie::CATEGORIEEN`). Verwijderen vergrendeld: afdeling met medewerkers/onderliggende teams of functie met medewerkers → 422; afdeling niet onder zichzelf. Alle mutaties gelogd. Routes in `rol:hrmedewerker,beheerder`. View: onder de afdelingenboom twee beheerblokken met toevoegformulier + inline-bewerkbare rijen (HTML5 `form`-attribuut); alleen bij `magHrBeheer`, Bestuur alleen-lezen. 8 tests (`HrOrganisatiebeheerTest`); 450 groen. |
| 2026-07-11 | **Module HR / Personeelszaken — Fase F (self-service "Mijn HR" & iCal-agenda).** Eigen 360°-dossier `hr.mijn` (`Hr\MijnHrController`) voor elke gekoppelde medewerker: gegevens/dienstverband, verlofsaldo, aanvragen, gesprekken, documenten en checklists — alleen-lezen, autorisatie via `medewerkers.user_id` (geen aparte rol; `eigenMedewerker()`→403). iCal-export `hr.mijn.agenda` (`App\Support\Icsagenda`): VCALENDAR met geplande gesprekken + goedgekeurd verlof als all-day VEVENT's, bewust een zelfstandig `.ics`-bestand (geen live Outlook/Teams-koppeling, intranet-only). Eigen documentdownload `hr.mijn.document` met eigendomscheck + INZAGE-log. Zelfservice-menu (Mijn HR + Mijn verlof) toegevoegd aan elk menu wanneer de gebruiker een medewerkerrecord heeft (ook buiten de HR-module). HR-dashboard uitgebreid met een paneel actuele ziekmeldingen + Mijn HR-snelkoppeling. 5 tests (`HrMijnTest`); 442 groen. |
| 2026-07-11 | **Schoolbestuur — één samengevoegd overzicht met alle modules (opdrachtgever).** De twee Overzicht-links (Bestuur + Dashboard) zijn samengevoegd tot **één** pagina (`bestuur`); de sidebar heeft nog één link (Bestuursoverzicht) en `DashboardController` redirect het Bestuur na login daarheen. `BestuurController` verzamelt nu de statistieken van **alle** modules via hun eigen aggregatieklassen: Studentenzaken (`Statistiek`), Cursussen (`Cursusrapport`), Relatiebeheer & Stage (`Relatierapport`, scope null) en HR (`HrRapport`, scope null). De view is geordend in vijf **rubrieken met hoofdlijnen** (A Studenten & onderwijs, B Financiën, C Cursussen, D Relatiebeheer & stage, E HR / Personeelszaken). **Financiën is expliciet tweeledig** (opheldering opdrachtgever): collegegeld (opleidingen) én cursusgelden (cursussen) apart getoond, plus een gecombineerd totaal — zo is duidelijk dat het beide geldstromen betreft. 10 tests (`BestuurPaginaTest`). |
| 2026-07-11 | **Rolsamenvoeging HR-medewerker + Manager (opdrachtgever).** Bij IUASR zijn de HR-medewerker en de leidinggevende dezelfde persoon; de twee rollen zijn samengevoegd tot één rol `Rol::Hrmedewerker`. Enum-case `Manager` verwijderd (+ alle exhaustive matches, `magHrInzien`, `isHrTeamBeperkt()`→`false`, `magVerlofBeoordelen`); `Verlofaanvraag::beoordeelbaarVoor` vereenvoudigd; `GesprekController::gespreksvoerders` zonder Manager; HR-routemiddleware van `rol:hrmedewerker,manager,…` naar `rol:hrmedewerker,…`; sidebar-fallback zonder Manager. Datamigratie `merge_manager_in_hrmedewerker` (`manager`→`hrmedewerker` + enum-drop; no-op op verse test-DB). `HrSeeder`: Ruben Smit nu `Hrmedewerker` (blijft leidinggevende in de organisatiestructuur). Het scopingsmechanisme (`Medewerker::scopeZichtbaarVoor`) blijft aanwezig maar is nu een no-op; de team-scopingtests zijn omgezet naar "de gecombineerde rol ziet iedereen". |
| 2026-07-11 | **Module HR / Personeelszaken — Fase E (onboarding/offboarding).** Enum `ChecklistSoort` (onboarding/offboarding) met een `sjabloon()`-standaardtaaklijst per soort; tabel `hr_checklisttaken` (medewerker, soort, titel, verantwoordelijke, volgorde, gereed + gereed_op/gereed_door). Op de medewerkerkaart start HR/Manager een checklist (de sjabloontaken worden aangemaakt — idempotent, niet als de checklist al bestaat), vinkt taken af (met datum en wie), voegt eigen taken toe en ziet de voortgang. `Hr\ChecklistController` (start/store/toggle/destroy); autorisatie via `magHrInzien` + `medewerker->zichtbaarVoor` (Manager = eigen team); starten gelogd. Synthetische seed (demo-onboarding voor Johan Bakker) + guarded datamigratie. 5 tests (`HrChecklistTest`); 435 groen. |
| 2026-07-11 | **Module HR / Personeelszaken — Fase D (organisatiestructuur & rapportages).** Geen nieuwe tabellen — de afdelingenboom (`afdelingen.bovenliggende_afdeling_id` + `manager_id`) en functies bestonden al. Aggregatieklasse `App\Support\HrRapport` (teamgebonden gescoped via `?array $ids`): kerncijfers (medewerkers, FTE-totaal/-gemiddeld, actueel **verzuimpercentage** = aandeel met open ziekmelding, ziektedagen dit jaar), `perAfdeling` (aantal/FTE/verzuim%) en `rijen` (per medewerker, CSV). Nieuwe pagina's **Rapportage** (`hr.rapport` + CSV-export, gelogd) met tegels, chart-partials en de per-afdeling-tabel, en **Organisatie** (`hr.organisatie`) met de recursieve afdelingenboom (afdeling → team, manager, aantal medewerkers). Scope: Manager = eigen team, HR/Beheer/Bestuur = alles. Seed uitgebreid met een sub-afdeling **PABO-team** onder Onderwijs (teamleden verplaatst) + guarded datamigratie. Sidebar: Organisatie + Rapportage. 6 tests (`HrRapportTest`); 430 groen. |
| 2026-07-11 | **Module HR / Personeelszaken — Fase C (gesprekken & performance).** Enums `Gesprekstype` (beoordeling/functionering/exit) en `Gespreksstatus` (gepland/gehouden/afgerond). Tabellen `gesprekken` (type/datum/gespreksvoerder/status/samenvatting/feedback), `gespreksdoelen` (KPI's; open/behaald/niet_behaald) en `competentiescores` (competentie + score onvoldoende..uitstekend). HR/Manager plant een gesprek op de medewerkerkaart; op het gespreksscherm worden de samenvatting/feedback + doelen + competenties inline beheerd. Historie per medewerker, module-overzicht (`gesprekken`) en dashboardsignalering (aankomende geplande gesprekken). Team-scoping via `Gesprek::beheerbaarVoor` (= `magHrInzien` + `medewerker->zichtbaarVoor`), dus een manager voert uitsluitend gesprekken van het eigen team; mutaties gelogd. Synthetische seed + guarded datamigratie. 6 tests (`HrGesprekTest`); 424 groen. |
| 2026-07-11 | **Module HR / Personeelszaken — Fase B (verlof & verzuim).** Enums `Verloftype` (vakantie/bijzonder/ouderschap/studie) en `Verlofstatus`. Tabellen `verlofsaldi` (recht per medewerker/jaar/type), `verlofaanvragen` en `ziekmeldingen`. **Self-service**: elke medewerker met een gekoppeld account vraagt zelf verlof aan (`verlof.mijn`, geen rolbeperking, controller vereist een `medewerker`-record) → **goedkeuring door de leidinggevende** (HR als terugval; een manager kan nooit de eigen aanvraag beoordelen, `beoordeelbaarVoor`) → registratie. **Saldo** per type afgeleid: recht − opgenomen (som goedgekeurde aanvragen in het jaar) via `App\Support\Verlofoverzicht`; HR stelt het recht in op de medewerkerkaart. **Verzuim**: ziek-/herstelmelding die de medewerkerstatus op ziek/actief zet; verzuimoverzicht (open/alle). Panelen Verlof/Verzuim op de medewerkerkaart + overzichten + dashboardsignalering (openstaande aanvragen). Team-scoping: manager ziet eigen team, HR/Beheer iedereen. Beoordelen/ziekmelding gelogd. Synthetische seed + guarded datamigratie. 8 tests (`HrVerlofTest`); 418 groen. **Hiermee is de HR-MVP (Fasen A + B) compleet; C t/m G volgen.** |
| 2026-07-11 | **Module HR / Personeelszaken — Fase A (medewerkersregistratie).** Nieuwe module (placeholder `hr` geactiveerd) voor personeelsadministratie; ontwerp in `docs/MODULE-HR-PERSONEELSZAKEN.md`. Twee nieuwe rollen **HR-medewerker** + **Manager** (enum + ALTER). Keuzes opdrachtgever: stack blijft **Laravel/MySQL/Entra** (niet de generieke React/Node-suggestie uit de prompt), UI **Nederlands** (EN-i18n later); **voltijdsnorm 40 uur** met **afgeleide FTE**; **BSN veld klaar maar standaard uit** (config `sis.hr.bsn_ingeschakeld`, akkoord FG), versleuteld + gelogd zoals bij studenten; verlof-flow (Fase B) = **manager keurt goed, HR terugval**. Datamodel: `functies`, `afdelingen` (teamboom + afdelingsmanager), `medewerkers` (leesbaar personeelsnummer P+jaar+volgnr; self-service via `user_id`), `dienstverbanden` (contracthistorie, FTE = uren ÷ 40) en `hr_documenten` (private schijf, gelogd). Medewerkers-CRUD + medewerkerkaart (dienstverband- en documentpaneel) + HR-dashboard (aantallen, FTE-totaal, statusverdeling, aflopende contracten). **Team-scoping:** de Manager ziet uitsluitend het eigen team (`Medewerker::scopeZichtbaarVoor` via `manager_id`), HR/Beheer/Bestuur iedereen. Functies/afdelingen beheerbaar via Opzoektabellen. Mutaties gelogd. Synthetische `HrSeeder` (3 afdelingen, 4 functies, 6 medewerkers, HR-accounts Nadia Aslan/Ruben Smit) + guarded datamigratie. 8 tests (`HrModuleTest`); 410 groen. MVP = Fasen A + B; B t/m G volgen. |
| 2026-07-11 | **Relatiebeheer: een eigen relatiebeheerder én stagecoördinator per opleiding.** Wens opdrachtgever: de drie opleidingen (PABO, Bachelor Islamitische Theologie, Master IGV) strikt gescheiden houden — elke opleiding beheert haar eigen relaties én stages zelf. Voorheen dekte één stagecoördinator (Tarik Ozan) ISLTH + MGV en had PABO géén stagecoördinator (alleen de Beheerder kon PABO-stages plaatsen). Nu heeft **elke opleiding een eigen relatiebeheerder én een eigen stagecoördinator**, elk aan **precies één** opleiding gekoppeld (via `directie_opleidingen`): PABO = Laila Haddad (relatiebeheerder) + Ilse Vermeer (stagecoördinator); ISLTH = Karim Belkacem + Tarik Ozan; MGV = Amina Cherif + Joost Prins. Namen synthetisch, aanpasbaar via Gebruikers & rollen. Aangepast in `GebruikerSeeder` (fresh/tests) én via de guarded datamigratie `relatiebeheer_per_opleiding` op de draaiende DB (zet Tarik op ISLTH, maakt de vier ontbrekende accounts). De acht relatiebeheer-tests selecteren hun gebruikers nu op e-mail (deterministisch): relatiebeheerder = PABO/Laila, stagecoördinator = MGV/Joost Prins. 402 groen. |
| 2026-07-11 | **Module Relatiebeheer & Stagebeheer — Fase H (slimme functies) + module afgerond.** Intranet-veilige slimme functies: (1) **globaal zoeken** (`relatiebeheer.zoeken`, `ZoekController`) over organisaties, contactpersonen én stages tegelijk, opleidinggebonden gescoped; (2) **iCal-export** (`relatiebeheer.agenda.ics`, `AfspraakController@ical`) van de aankomende afspraken als standaard `.ics` — te importeren in Outlook/Google/Apple (een download, geen live sync); (3) **actiepunt → taak** (`contactmomenten.taak`, `ContactmomentController@maakTaak`): van een contactmoment met vervolgdatum een opvolgtaak maken (422 zonder vervolgdatum). Keuze: **tweewegs Outlook/Graph-synchronisatie en e-mail-koppeling blijven bewust buiten scope** (vergen een externe provider; passen niet in het intranet-only regime). Geen nieuwe migraties. 5 tests (`RelatieSlimmeFunctiesTest`); 402 groen. **Hiermee is de module Relatiebeheer & Stage (Fasen A–H) volledig afgerond.** |
| 2026-07-11 | **Module Relatiebeheer & Stagebeheer — Fase G (dashboard & rapportage).** Aggregatieklasse `App\Support\Relatierapport` (opleidinggebonden gescoped via een optionele `?array $oplIds`; null = geen beperking). De module opent voortaan op een **Overzicht/dashboard** met kerncijfers (organisaties, contactpersonen, stageplaatsen + bezettingsgraad, lopende/te-beoordelen stages, open taken, aankomende bezoeken, aflopende contracten, nieuwe documenten, % voldoende), grafieken (stages per status, organisaties per type) en een lijst 'te beoordelen stages'. Nieuwe **Rapportage**-pagina met een overzicht per organisatie en een **CSV-export** (gelogd als INZAGE). Startroute verzet naar `relatiebeheer.dashboard`; de organisatielijst is verhuisd naar `/organisaties` met behoud van de routenaam `relaties`. Hergebruik van de bestaande chart-partials en `Statistiek`-stijl. Sidebar kreeg Overzicht + Rapportage. Geen nieuwe migraties (alleen-lezen). 5 tests (`RelatieDashboardTest`) + `DashboardStatistiekTest` bijgewerkt; 397 groen. |
| 2026-07-11 | **Module Relatiebeheer & Stagebeheer — Fase F (documenten & overeenkomsten).** Tabellen `relatie_documenten` (private schijf, categorieën, **versiebeheer** via `vorige_versie_id`; upload/inzage/verwijdering gelogd — AVG) en `overeenkomsten` (enums `OvereenkomstType`/`OvereenkomstStatus`, start/verloopdatum). Op de relatiekaart panelen **Documenten** (inline upload + 'nieuwe versie' terwijl de oude bewaard blijft) en **Overeenkomsten**. **Digitale ondertekening hergebruikt de bestaande module**: een meegestuurde PDF gaat door `Documentondertekening::ondertekenUpload()` (SHA-256 + verificatiecode + waarmerk-certificaat), wordt gekoppeld en de status wordt Getekend; download via een eigen module-route (gelogd), zodat de module-rollen niet afhankelijk zijn van de rolgroep van het ondertekenarchief. Signalering **'contracten die verlopen'** (verloopdatum ≤ 60 dagen of verstreken, niet opgezegd) op de planning (`agenda`) en als badge op de kaart. Muteren = wie de organisatie beheert; downloads ook voor Directie/Bestuur (gelogd). Synthetische seed (1 overeenkomst) + guarded datamigratie. 6 tests (`DocumentOvereenkomstTest`, incl. echte waarmerking met `Storage::fake`); 392 groen. |
| 2026-07-11 | **Module Relatiebeheer & Stagebeheer — Fase E (taken & agenda).** Tabellen `relatie_taken` (eigen tabel naar Outlook/Graph `todoTask`, met hergebruik van de enums `TaakStatus`/`TaakPrioriteit`; 'te laat' afgeleid uit vervaldatum + status) en `agenda_afspraken` (enum `AfspraakType`: schoolbezoek/stagebezoek/evaluatie/overleg/open dag; status gepland/afgerond/geannuleerd). Op de relatiekaart een **Taken-paneel** (inline toevoegen, afvinken/heropenen, prioriteit, toewijzing) en een **Agenda-paneel** (afspraken plannen/bewerken); daarnaast een module-brede **planning** (`agenda`) met de aankomende afspraken én de openstaande taken binnen het bereik van de gebruiker (dashboardsignalering). Keuze/afdwinging: de koppeling loopt via de organisatie — muteren mag wie de organisatie beheert (relatiebeheerder/stagecoördinator eigen opleiding + Beheer); Directie/Bestuur zien mee. Geen audit-logging (werkverdeling, geen gevoelig gegeven). Aparte tabel i.p.v. de SZ-`taken` zodat de administraties gescheiden blijven. Synthetische seed (1 taak + 1 afspraak) + guarded datamigratie. 7 tests (`TakenAgendaTest`); 386 groen. Tevens `phpunit.xml` voorzien van `memory_limit=1024M` zodat `php artisan test` de volledige suite weer draait (piekte boven de standaard 128M). |
| 2026-07-11 | **Module Relatiebeheer & Stagebeheer — Fase D (stagebeheer).** Enum `Stagestatus` (aangevraagd/lopend/afgerond/afgebroken). Tabellen `stageplaatsen` (aanbod/capaciteit per opleiding — aantal/max/werkdagen/eisen; bezetting afgeleid uit lopende stages) en `stages` (leesbaar `stagenummer`; student ↔ organisatie/stageplaats, **stagebegeleider** = docent-gebruiker vanuit de opleiding, **werkplekbegeleider** = contactpersoon op locatie; start/eind, status, beoordeling voldoende/onvoldoende + toelichting). Stageplaatsen beheren op de relatiekaart; studenten plaatsen via "Student plaatsen"; een module-breed **Stages-overzicht** met filters (status/opleiding/organisatie). Keuze opdrachtgever (Fase A): beoordeling **voldoende/onvoldoende** — gevoelig, gelogd als `stage_beoordeling`. Rolscheiding: muteren = **Stagecoördinator** (eigen opleiding, `magStagebeheer`) + Beheer; de Relatiebeheerder beheert de organisatie maar niet de plaatsing; Directie/Bestuur zien mee (alleen-lezen). Server-side afgedwongen: opleiding hoort bij de organisatie én binnen de scope, de student heeft een actieve inschrijving in die opleiding, stageplaats en werkplekbegeleider horen bij dezelfde organisatie, de stagebegeleider heeft rol Docent (alles via `Rule::in`/`exists`). Synthetische seed (3 stageplaatsen + 1 demo-stage) + guarded datamigratie. 7 tests (`StageTest`); 379 groen. |
| 2026-07-11 | **Module Relatiebeheer & Stagebeheer — Fase C (contactmomenten, notities & tijdlijn).** Tabellen `contactmoment_types` (opzoektabel; standaardtypes telefoon/e-mail/Teams/bezoek/stagebezoek/overleg/netwerk/klacht/evaluatie), `contactmomenten` (type, contactpersoon, medewerker, datum/tijd, onderwerp, samenvatting, vervolgdatum) en `relatie_notities` (categorie, tags, tekst). Contactmomenten registreren/corrigeren op de relatiekaart (historisch — niet verwijderen), gelogd; notities toevoegen/verwijderen (werkinformatie, niet gelogd). Nieuw paneel **Historie/tijdlijn**: afgeleid (`App\Support\Relatietijdlijn`) uit contactmomenten + notities + audit-log (organisatie én contactpersonen), chronologisch — geen aparte historietabel. Keuze/afdwinging: een gekoppelde contactpersoon moet bij dezelfde organisatie horen (`Rule::in`, server-side). Autorisatie volgt de organisatie (`beheerbaarVoor`); Directie/Bestuur zien de tijdlijn alleen-lezen. Contactmoment-types beheerbaar via Opzoektabellen. Synthetische seed + guarded datamigratie voor de draaiende DB. 6 tests (`ContactmomentTest`); 372 groen. |
| 2026-07-11 | **Module Relatiebeheer & Stagebeheer — Fase B (contactpersonen & relatiekaart).** Tabel `contactpersonen` (FK organisatie, cascade; voornaam/achternaam/functie/afdeling/e-mail/mobiel/telefoon/voorkeurskanaal/LinkedIn/actief) + model `Contactpersoon`. `ContactpersoonController` — CRUD genest onder de organisatie, autorisatie volgt de organisatie (`beheerbaarVoor`); alleen wie de organisatie mag beheren (relatiebeheerder/stagecoördinator eigen opleiding, Beheer) muteert de contactpersonen. De **relatiekaart** (`relaties.show`) toont voortaan een contactpersonen-paneel met toevoegen/bewerken/**inactiveren** (geen verwijderen — historie/AVG); voorkeurskanaal e-mail/telefoon/Teams. Mutaties gelogd (`veld: contactpersoon`). Synthetische contactpersonen in `OrganisatieSeeder` + guarded datamigratie voor de draaiende DB. 5 tests (`ContactpersoonTest`); 366 groen. |
| 2026-07-11 | **Module Relatiebeheer & Stagebeheer — Fase A (organisaties).** Nieuwe, opleidingoverstijgende onderwijs-CRM (PABO, Bachelor Islamitische Theologie, Master IGV) voor stagescholen/werkveldrelaties. Keuzes opdrachtgever: (1) **twee nieuwe rollen** relatiebeheerder + stagecoördinator (overige rollen hergebruikt); (2) stagebeoordeling **voldoende/onvoldoende**; (3) stageterminologie én **organisatietypes per opleiding configureerbaar** (opzoektabel `organisatie_types` met `opleiding_id`, beheerd via Opzoektabellen). Implementatie: de platform-placeholder-module `stage` is hernoemd tot `relatiebeheer` en geactiveerd (guarded migratie, geen dubbele module); rollen via enum + ALTER. Datamodel `organisaties` (leesbaar `relatienummer` R+jaar+volgnr) + pivot `organisatie_opleidingen`; **opleidinggebonden zichtbaarheid** hergebruikt de `directie_opleidingen`-koppeling (`isRelatieBeperkt`, `Organisatie::scopeZichtbaarVoor`). `OrganisatieController` (CRUD + filterbalk + inactiveren i.p.v. verwijderen), routes onder prefix `relatiebeheer`, eigen sidebar-tak, 360°-relatiekaart (basis). **AVG-grens (hard):** uitsluitend organisatie-/contactgegevens, nooit leerling-/cliëntgegevens — de stageschool blijft verwerkingsverantwoordelijke; aanmaken/wijzigen gelogd; een opleidinggebonden gebruiker kan alleen de eigen opleiding(en) koppelen (server-side). Ontwerp vastgelegd in `docs/MODULE-RELATIEBEHEER.md` (fasen A–H). Synthetische seed + guarded datamigratie voor de draaiende DB. 10 tests (`RelatiebeheerModuleTest`); 361 groen. Fasen B–H volgen. |
| 2026-07-11 | **Eigen opleiding/cursus zichtbaar voor de ingelogde directeur/cursusdirecteur.** Een ingelogde directeur zag alleen "Directie", niet van welke opleiding. Nu staat de opleiding(en)/cursus(sen) op drie plekken: (1) de **topbalk** (`partials/header`) naast de rol als pill; (2) het **modulekeuzescherm** naast de naam, bijv. "Directie · MGV"; (3) het **dashboard** — de kop toont "Directie — <codes>" en de subtitel de volledige opleidingnaam/-namen. Voor de rol Cursusadministratie idem met de cursuscodes. Een directeur zonder toewijzing krijgt een duidelijke melding "Nog geen opleiding toegewezen" (topbalk + dashboard). 4 tests (`DirectieOpleidingZichtbaarTest`); 351 groen. |
| 2026-07-11 | **Opleiding-/cursuscode in de gebruikerslijst.** Op verzoek (duidelijkheid): in Beheer → Gebruikers & rollen staat nu naast de rol de **afkorting (code)** van de gekoppelde opleiding(en)/cursus(sen). Voor de rol **Directie** de opleidingcodes (bijv. ISLTH, PMGV) uit `directie_opleidingen`; voor de rol **Cursusadministratie** de cursuscodes (bijv. ARAB-TAAL, HIFZ, IJAZA) uit `cursussen.directeur_id` (via `User::gedirigeerdeCursussen()`, nu mee-geëchoload in `GebruikerController` tegen N+1). Getoond als kleine pills met de volledige naam als tooltip. Ook de directie-toewijzingskaart toont de codes (pills naast de naam + code in de vinkje-labels). 3 tests (`GebruikerlijstOpleidingCodeTest`); 347 groen. |
| 2026-07-10 | **Directie per opleiding herverdeeld (aparte directeur per opleiding).** Wens opdrachtgever: geen directie-account dat alle opleidingen ziet. Verdeling: Bachelor Islamitische Theologie (ISLTH) + Pre-Master GV (PMGV) → één directeur (Bram de Wit); Master GV (MGV) → eigen directeur (Yasin Demir); PABO → eigen directeur (Mariëlle Groen). Elke directeur ziet en beheert uitsluitend de eigen opleiding(en) (de bestaande opleidinggebonden scoping via `directie_opleidingen`). Aangepast in de `GebruikerSeeder` (fresh/tests) én via de guarded datamigratie `directie_per_opleiding_herverdelen` op de draaiende DB (maakt ontbrekende directie-accounts aan en synct de koppelingen; no-op op een verse migratie). De cursus-opleidingen KRN/ARAB blijven bewust ongekoppeld (buiten de opdracht) — Beheer kan ze desgewenst toewijzen via Gebruikers & rollen. Directie-afhankelijke tests maken hun eigen users aan of koppelen de opleiding expliciet, dus 344 groen. |
| 2026-07-10 | **Snelkoppeling Vrijstelling + alumni-rapport voor de Examencommissie.** Twee wensen: (1) In de Examencommissie-sidebar staat onder Studenten de knop **Vrijstelling** → direct naar de studentenlijst met `?doel=vrijstelling` (banner "Kies een student…"); de "Openen"-knop deeplinkt dan naar `#vrijstelling` op het dossier, waar het vrijstellingsbesluit-formulier staat. De sidebar-renderer kreeg optionele query-parameters (5e element) en een doel-bewuste actief-markering. (2) De Examencommissie mag nu het **alumni-rapport** zien (kreeg eerst een 403): route `rapporten.alumni` uitgebreid met `examencommissie`, plus menu-item Rapporten → Alumni. Het rapport bevat geen cijfers/BSN en de examencommissie is niet opleidinggebonden (ziet alle alumni). Test die de weigering afdwong omgezet naar toestemming. 3 tests (`VrijstellingSnelkoppelingTest`) + alumni-test bijgewerkt; 344 groen. |
| 2026-07-10 | **Studenten opschonen (commando) + verwijderd 261015-261026.** Voor het testen met collega's op een schone dataset: artisan-commando `sis:studenten-verwijderen {nummers*} {--force}` dat studenten op nummer (los of als reeks, bijv. `261015-261026`) verwijdert. Standaard dry-run (voorbeeldtabel); met `--force` definitief, in een transactie, per student gelogd (VERWIJDERING, veld `student`). Alle gekoppelde gegevens ruimen via de DB-constraints: inschrijvingen/betalingen/resultaten/presenties/vaktoewijzingen/notities/documenten/vrijstellingsbesluiten/betalingsafspraken/kennistoetsresultaten op ON DELETE CASCADE; taken en ondertekende documenten op SET NULL (blijven bestaan, koppeling vervalt). Valkuil onderweg: de studententabel heet `studenten`, niet `students` — een raw `DB::table('students')` faalde; nu via het Eloquent-model (correcte tabel) dat de DB-cascades benut. Uitgevoerd op de dev-DB: **261015 t/m 261026 (12 synthetische studenten) verwijderd** (26 → 14 studenten; 28 → 16 inschrijvingen; 396 → 388 presenties), gelogd. 5 tests (`VerwijderStudentenTest`); 341 groen. |
| 2026-07-10 | **Jaarovergang naar studiejaar 2026-2027.** Het studiejaar 2025-2026 is afgelopen; het systeem is klaargezet voor testen met collega's in 2026-2027. Audit vooraf (code + database): het systeem is volledig **periode-geparametriseerd** — precies één `perioden`-rij is `actief` (afgedwongen door het `Periode::saved`-event), en alle runtime-logica leidt het jaar af uit de **periode van de inschrijving** of uit `now()`; geen enkel studiejaar staat hardcoded in een berekening (termijndata uit `periode.startdatum`, tarief uit `collegegeldtarieven` op `periode_id`). De **collegegeldtarieven 2026-2027 stonden al klaar** (ISLTH €3.500, PMGV €3.500, MGV €4.000, PABO €3.500; cursussen KRN/ARAB hebben bewust geen tarief). Uitgevoerd: de actieve periode op de **draaiende database** omgezet naar 2026-2027 via de guarded datamigratie `activeer_studiejaar_2026_2027` (raw update omdat een migratie geen Eloquent-event vuurt; alle jaren inactief, daarna 2026-2027 actief). Bewust **niet** de `ReferentieSeeder`-basislijn gewijzigd: die blijft 2025-2026 actief als testfixture (een seederwijziging brak ~40 tests door tariefcollisies en de "2026-2027 = komend jaar"-aannames — de jaarovergang is per ontwerp een data-/UI-operatie, geen fixturewijziging). De migratie no-opt op een verse test-DB (perioden nog leeg), dus alle tests blijven groen. 2025-2026 blijft als **historie** bewaard; studenten schuiven per stuk mee via Herinschrijven; verdere overgangen stuurt Beheer via Opzoektabellen → Studiejaren. Geverifieerd op de dev-DB: 13 actieve inschrijvingen in 2026-2027 met correct berekend collegegeld. 336 groen. |
| 2026-07-10 | **Cursus kopiëren (kopie-wizard, Beheerder).** Op verzoek: met één klik een nieuwe cursus starten als kopie van een bestaande (bijv. Turkse Taal naar het voorbeeld van Arabische Taal). Knop **Kopiëren** in het Cursusbeheer (alleen Beheerder) → `CursusController@kopieForm` (route `cursussen.kopieren`, `/beheer/{bron}/kopieren`) rendert het bestaande cursusformulier vooraf ingevuld met de brongegevens (cursusgeld, omschrijving, looptijd, directeur; code leeg). Opslaan loopt via de reguliere `store`; geen aparte kopie-actie nodig. **Cursisten worden niet meegekopieerd** — de nieuwe cursus begint leeg. Bug tijdens bouw: routeparameter heette `{cursus}` terwijl de methode `$bron` verwachtte → route-model-binding gaf een leeg model (alle voorinvulvelden leeg); opgelost door de parameter `{bron}` te noemen. 2 tests in `CursussenModuleTest`. |
| 2026-07-10 | **Bugfix: Cursusbeheer-pagina gaf 500 (Blade) + dummy-cursisten.** De sidebar-link **Cursusbeheer** leidde naar een 500: in `cursussen/beheer.blade.php` stond `@endunless` vastgeplakt aan een woord (`beheer@endunless`); Blade herkent een directive niet als er direct een letter vóór staat (`\B`-regel), waardoor de `@unless` open bleef en de view niet compileerde — voor álle rollen. Vervangen door een ternary; regressietest toegevoegd (`test_cursusbeheer_pagina_rendert`). Dezelfde valkuil (latent, alleen zichtbaar na een CSV-import) opgespoord en verholpen in `financien/index.blade.php` (`geïmporteerd@if(...)`). Verder een **`SynthetischeCursistSeeder`** toegevoegd: een handvol synthetische cursisten per cursus met wisselende betaalstatussen (volledig/deels/openstaand), zodat dashboard, cursusgelden en rapportage meteen gevulde cijfers tonen (idempotent, vaste cursistnummers C269001+). 334 groen. |
| 2026-07-10 | **Directe cursusknoppen op het welkomstscherm + cursus-startpagina.** Wens opdrachtgever: na het inloggen op het keuzescherm een **aparte knop per cursus**, zodat een gebruiker meteen de eigen cursus opent i.p.v. eerst de hele module te doorzoeken. `ModuleController` geeft de zichtbare, actieve cursussen mee (`Cursus::query()->zichtbaarVoor()`); het keuzescherm toont ze als tegels onder "Cursussen" (cursusdirecteur = eigen cursus[sen]; Financiën/Beheer/Bestuur = alle). Elke tegel opent de nieuwe **cursus-startpagina** `cursussen.cursus` (`/cursussen/cursus/{cursus}`, `abort_unless(zichtbaarVoor)`) met statusverdeling, financiële kerncijfers en de cursisten van die cursus, plus snelkoppelingen naar cursusgelden/rapportage. Een verkeerde keuze wordt server-side geblokkeerd (403). Op verzoek toont de cursus-tegel **geen cursusgeld** meer (alleen naam + code). **Directeurverdeling (opdrachtgever):** Arabische Taal is een aparte cursus met een eigen directeur (Hafsa); Hifz én Ijaaza worden door dezelfde directeur (Omar) beheerd — twee directeuren, aangepast in de `GebruikerSeeder` en via een guarded datamigratie voor de bestaande DB. 6 extra tests in `CursusdirecteurTest`; 333 groen. |
| 2026-07-10 | **Cursusrapportage & dashboards (module Cursussen, Fase E).** Nieuwe pagina **Rapportage** (`cursussen.rapport`) met per cursus de inschrijvingen (uitgesplitst naar status) en de cursusgelden (verschuldigd/betaald/openstaand + betaalgraad), totalen, grafieken (inschrijvingen per cursus, openstaand per cursus, betaalmethode-donut) en een **CSV-export** op cursistniveau (gelogd als INZAGE). Aggregatie in `App\Support\Cursusrapport`. Keuzes/ontwerp: (1) de financiële cijfers tellen alleen **niet-geannuleerde** inschrijvingen (een geannuleerde inschrijving is geen schuld); (2) alleen betalingen met status **Betaald** gelden als voldaan; (3) dezelfde **scoping** als het dashboard — een cursusdirecteur ziet uitsluitend de eigen cursus(sen), Financiën/Beheer/Bestuur alle cursussen; (4) het rapport is voor alle vier de moduletoegangsrollen (`rol:cursusadministratie,financien,beheerder,bestuur`). Het **cursusdashboard** kreeg twee financiële KPI's (betaalgraad, openstaand cursusgeld) en een Rapportage-knop; de **Bestuurspagina** linkt door naar het cursusrapport. Hergebruikt de chart-partials (bar/donut). 8 tests (`CursusrapportTest`); 327 groen. Hiermee is de **module Cursussen Administratie** (Fasen A–E) afgerond. |
| 2026-07-10 | **Bestuurspagina + systeembeheer-snelkoppelingen op de hoofdpagina.** Twee wensen opdrachtgever: (1) de **Beheerder**-taken (back-up, gebruikers & rollen, opzoektabellen, audit-log, technische handleiding) waren alleen bereikbaar via de Studentenzaken-sidebar — nu staan ze als blok **Systeembeheer** op de **modulekiezer** (hoofdpagina). De routes blijven `rol:beheerder`; het zijn snelkoppelingen. (2) Het **Schoolbestuur** krijgt een eigen, instellingsbrede **Bestuurspagina** (`/bestuur`, `rol:bestuur,beheerder`, `BestuurController` → `bestuur.index`), alleen-lezen: kerncijfers en grafieken over studenten, studiesucces, aanwezigheid, collegegeld én cursussen op één scherm. Hergebruikt de bestaande `Statistiek`-aggregaties + de chart-partials (bar/spark/donut) en dashboardkaarten, plus cursuscijfers. Bereikbaar via een tegel op de modulekiezer en een sidebar-item (Bestuur + Beheerder). 7 tests (`BestuurPaginaTest`); 319 groen. |
| 2026-07-10 | **Cursusdirecteuren — toegang per cursus (module Cursussen, Fase D).** De rol Cursusadministratie is voortaan cursusgebonden: elke cursusdirecteur ziet en beheert uitsluitend de eigen cursus(sen) en de cursisten/inschrijvingen daarop (need-to-know, analoog aan de opleidinggebonden Directie). Keuzes opdrachtgever/ontwerp: (1) sleutel is de bestaande FK `cursussen.directeur_id` — één directeur per cursus, een directeur kan meerdere cursussen hebben; geen pivottabel; (2) cursus **aanmaken/verwijderen en de directeur toewijzen is voorbehouden aan de Beheerder** (server-side; in `update` wordt `directeur_id` alleen toegepast als de gebruiker Beheerder is → een directeur kan zichzelf geen cursussen toekennen), een directeur mag wél de eigen cursusgegevens wijzigen; (3) de **boekhouding** (Financiën) blijft ongescoped — alle cursussen; (4) het **Schoolbestuur** krijgt de module met dashboard/statistieken én cursistinzage, **alleen-lezen** (moduleSleutels Bestuur = studentenzaken+cursussen; de mutatieknoppen hangen in de views aan `magCursusBeheer()`). Implementatie: `User::gedirigeerdeCursussen()/cursusIds()/isCursusBeperkt()`, `Cursus::/Cursist::scopeZichtbaarVoor()` (aan te roepen via `::query()->` wegens naamsbotsing met de instance-guard) + `Cursus::zichtbaarVoor()/beheerbaarVoor()`. Directeur toewijzen via het veld *Cursusdirecteur* op het cursusformulier (alleen Beheerder). Seed: Hafsa → ARAB-TAAL+HIFZ, Omar → IJAZA; guarded datamigratie `wijs_cursusdirecteuren_toe` vult een bestaande database (draait op een verse migratie vóór de seeders en doet dan niets). 15 tests (`CursusdirecteurTest` + tegentest in `CursussenModuleTest`); 312 groen. |
| 2026-07-10 | **Cursusgelden & boekhouding (module Cursussen, Fase C).** Tabel `cursusbetalingen` (bedrag, methode, datum, status, referentie, opmerking, geregistreerd-door FK). Enums **Betaalmethode** (iDEAL/online, bankoverschrijving, contant) en **Cursusbetaalstatus** (in afwachting/betaald/mislukt/terugbetaald). Keuzes opdrachtgever/ontwerp: (1) cursusgeld wordt **in één keer** verschuldigd — geen termijnschema zoals bij het collegegeld, wel **deelbetalingen** die optellen; (2) iDEAL is een **geregistreerde** methode, geen live betaalprovider — de boekhouding legt de ontvangen betaling vast; (3) alleen status **Betaald** telt mee voor het voldane bedrag; in afwachting/mislukt/terugbetaald niet; (4) registreren, **wijzigen en verwijderen** is voorbehouden aan de **Financiële Administratie** (boekhouding) + Beheer, alles gelogd (veld `cursusbetaling`, met oude/nieuwe waarden); de cursusadministratie ziet de status **alleen-lezen** op het cursistdossier. De financiële status per inschrijving is **afgeleid, niet opgeslagen** (`Cursusgeldstatus::voor()` → totaal/betaald/openstaand + voldaan/deels/open). Nieuwe pagina **Cursusgelden** (`CursusbetalingController`) met filters op cursus/status/zoek, per inschrijving een uitklapbaar beheerpaneel (historie + registreren + wijzigen/verwijderen). Toegang: het cursus**dashboard** is uitgebreid naar `rol:cursusadministratie,financien,beheerder` (de modulekiezer stuurt Financiën naar `cursussen.dashboard`), de betalingsroutes staan op `rol:financien,beheerder`, cursus-/cursistbeheer blijft `rol:cursusadministratie,beheerder`. Rolbewuste sidebar en dashboardknoppen via `Rol::magCursusBeheer()` / `magCursusFinancien()` (+ `User`-delegates). 10 tests (`CursusbetalingTest`); 297 groen. |
| 2026-07-12 | **Ruimere dummy-studentenpopulatie voor het testen (opdrachtgever 2026-07-12).** Voor het testen met collega's een generatieve `ExtraStudentenSeeder` toegevoegd die synthetische studenten over **alle leerjaren** aanmaakt (verzonnen namen uit naam-pools; geen echte personen, geen BSN). Verdeling: **ISLTH jr1:18 / jr2:12 / jr3:10 / jr4:8**, MGV jr1:10 / jr2:6, PMGV jr1:6, PABO jr1:9 / jr2:5 / jr3:4 / jr4:4 — totaal **94 studenten** (was 14). Studentnummers met cohort-jaarprefix (jr1→26, jr2→25, jr3→24, jr4→23), uniek t.o.v. de bestaande 261001–261014. Elke student krijgt meteen de **verplichte vakken van het leerjaar** toegewezen (`Vaktoewijzer`), dus ze zijn direct bruikbaar in cijferinvoer/aanwezigheid/rapportages — bv. Galal Ali's vak B-FQ02 heeft nu 13 deelnemers. Idempotent (firstOrCreate op studentnummer). Opgenomen in `DatabaseSeeder`; guarded migratie `extra_dummy_studenten` vult de draaiende DB (no-op op verse migratie). Valkuil onderweg: `taal_arabisch` is enum `TaalNiveau` (onvoldoende/voldoende/goed) — de waarde 'basis' brak de seed, gecorrigeerd. Dev-DB: 94 studenten, 96 inschrijvingen, 878 vaktoewijzingen. 500 groen. |
| 2026-07-12 | **Dummy-docenten opgeschoond + e-mailconventie + docent-login (opdrachtgever 2026-07-12).** De dummy-docenten **Yusuf Aydın** en **Salima Boujat** verwijderd; hun plek in de fixtures (`ReferentieSeeder`) ingenomen door twee echte docenten **Galal Ali** en **Mhamed Aarab**. Het docent-login (`GebruikerSeeder`) is nu **Galal Ali** (echt docentprofiel, onderwijst 7 Fiqh-vakken) i.p.v. Aydın, zodat "Mijn vakken" met echte vakken getest kan worden. `SynthetischVakSeeder` gebruikt dezelfde twee docenten (variabelen hernoemd). **E-mailconventie** vastgelegd: `{achternaam}@iuasr.nl` (dus `abba@iuasr.nl`, niet `a.abba@`), met uitzonderingen in `DocentSeeder::EMAIL_UITZONDERINGEN` (**Galal Ali → amer@iuasr.nl**); `DocentSeeder::emailVoor()` publiek+static gemaakt zodat de migratie hetzelfde adres afleidt. Guarded migratie `docenten_opschonen_en_email_conventie` past dit op de draaiende DB toe: alle docent-e-mails omgezet, Aydın-login verwijderd, Galal Ali-login aangemaakt, dummies verwijderd (vakken losgekoppeld) — met guard op verse migratie. Dev-DB: 17 docenten, Galal Ali-login met 7 vakken, e-mails `achternaam@iuasr.nl`. Rolscheiding-test vereist twee docenten met verschillende vakken (vandaar Aarab erbij). 500 groen. Handleiding (technisch) bijgewerkt. |
| 2026-07-12 | **Aanwezigheid: klikbaar vinkje i.p.v. dropdown (opdrachtgever 2026-07-12).** De docent vond de dropdown (1/0/–) omslachtig. Vervangen door een **aanklikbaar vakje** met drie standen: leeg → groen ✓ (aanwezig) → rood ✗ (afwezig) → leeg. Eén klik = aanwezig (de meest voorkomende actie); met "alle 1" de hele week aanwezig, daarna alleen de afwezigen aanklikken. Techniek: `<button>` + verborgen `input` per cel; de **opgeslagen waarden ('', '1', '0') en de controller/validatie blijven identiek**, dus geen wijziging aan `PresentieController` en alle tests blijven geldig. Legenda + savebar-tekst aangepast; alleen `presentie/lijst.blade.php` gewijzigd. 500 groen. Handleiding (medewerkers) bijgewerkt. |
| 2026-07-12 | **Docenten aan de vakken gekoppeld (opdrachtgever 2026-07-12).** Per vak de docent uit de studiegidsen (regel "Docent") gekoppeld: `database/data/vakdocenten.csv` (66 koppelingen) + `VakDocentSeeder` (na `DocentSeeder` in `DatabaseSeeder`). Match op **genormaliseerde achternaam** (diakrieten/hoofdletters/spaties/koppeltekens genegeerd, zodat "Biçer-Uslu" ↔ "Bicer-Uslu" en "Abu al Hijaa" ↔ "Abu Alhija" koppelen). Ontbrekende docent **Bouyazdouzen** toegevoegd aan `DocentSeeder` (alleen initiaal 'H.' bekend, niet zelf ingevuld). Gekoppeld: **ISLTH 48/60, MGV 18/19**; bewust géén individuele docent voor stage-/scriptiecoördinatie-vakken (B-ST01/02/04, B-BR01, M-GV17) en de 8 keuzevakken; **PMGV/PABO** geen bron. Guarded migratie `koppel_docenten_aan_vakken` past het op de draaiende DB toe — met **guard op verse migratie** (Vak::doesntExist), want `DocentSeeder` mag daar niet draaien: `ReferentieSeeder` hardcodeert later DOC-001/002 (zou botsen). Dev-DB: 66/91 vakken met docent. **Nog nodig voor "Mijn vakken":** een gebruikersaccount met rol Docent gekoppeld aan het docentprofiel (`users.docent_id`). 6 tests (`VakDocentTest`); 500 groen. Handleiding (technisch) bijgewerkt. |
| 2026-07-12 | **Toetsopbouw PMGV = dezelfde logica als ISLTH (opdrachtgever 2026-07-12).** De Pre-Master GV deelt vakcodes met de bachelor; op verzoek volgt elk PMGV-vak per code exact de toetsopbouw van het gelijknamige ISLTH-vak. 12 PMGV-vakken toegevoegd aan `toetsonderdelen.csv` (gegenereerd uit de ISLTH-rijen, dus identiek): B-QR02..05 (memorisatie/recitatie/schriftelijk 40/40/20), B-KL01/HD02/SG01/SG03/QR06 (tentamen 100%), B-KL03 (75/25), B-FQ06 (70/30). De gecombineerde code **B-FQ02-B-FQ03** ("Fiqh voor aanbidding I of II") volgt B-FQ02 (tentamen 100%). Guarded migratie `toetsopbouw_pmgv_uit_studiegids` her-runt de seeder op de draaiende DB. Dev-DB: 12 PMGV-vakken → 6 met meerdere onderdelen, 0 overgeslagen. Testhelper `onderdelen()` nu opleiding-bewust (ISLTH/MGV/PMGV delen codes). 1 extra test (`test_pmgv_volgt_dezelfde_logica_als_islth`); 494 groen. Handleiding (technisch) bijgewerkt. **Alleen PABO resteert** (studiegids nog niet beschikbaar). |
| 2026-07-12 | **Echte toetsopbouw MGV (Master IGV) uit de studiegids (opdrachtgever 2026-07-12).** De toetsonderdelen + weging van alle **19 MGV-vakken** toegevoegd aan `database/data/toetsonderdelen.csv`, ontleend aan de studiegids **MIGV 2024-2025** (`Documents/SGids MIGV 2024-2025 ver.1 HB literatuur.docx`; tekst geëxtraheerd via unzip van word/document.xml). Modules met 2–4 onderdelen, o.a. M-GV07 (Werkstuk 40% + Voordracht 20% + Rollenspel 25% + Moreel beraad 15%), M-GV08 (Werkstuk/Presentatie/Rollenspel/Reflectieverslag 50/20/20/10), M-GV16a/b (Opdracht 30% + Schriftelijke toets 70%); M-GV17 masterscriptie en M-GV18 stage elk 100%. Codes gekoppeld op de `Code:`-velden (normalisaties M-GV15A→M-GV15, M-GV16A/B→M-GV16a/M-GV16b). Guarded migratie `toetsopbouw_mgv_uit_studiegids` her-runt de seeder op de draaiende DB (de vorige draaide toen alleen ISLTH in de bron stond). Dev-DB: 19 MGV-vakken → 14 met meerdere onderdelen, 5 enkelvoudig, 0 overgeslagen. **PABO volgt later (studiegids nog niet beschikbaar).** 2 extra tests in `ToetsopbouwTest`; 493 groen. Handleiding (technisch) bijgewerkt. |
| 2026-07-11 | **Echte toetsopbouw ISLTH uit de studiegids (opdrachtgever 2026-07-11).** De werkelijke toetsonderdelen + weging per ISLTH-vak ingevoerd uit de studiegids 2025-2026 (hoofdstuk 5, per module de regel "Toetsing"). Bron in Git: `database/data/toetsonderdelen.csv` (52 vakken) + `ToetsonderdeelSeeder` (idempotent, matcht op opleiding+vakcode; vervangt de standaard 'Tentamen 100%' van `CurriculumSeeder`; **dataveilig**: vak met bestaande resultaten wordt overgeslagen). Guarded migratie `toetsopbouw_uit_studiegids` past het toe op de draaiende DB (no-op op verse migratie). **Genestte weging afgevlakt** naar losse onderdelen die optellen tot 100% (bv. "Schriftelijk 80% = grammatica 40% + vertalen 40%" + mondeling 20% → grammatica 0,40 / vertalen 0,40 / mondeling 0,20). Codes gekoppeld op de `Code:`-velden uit de module-secties (met normalisaties B-MT01A→B-MT01, B-MT04A/B→B-MT04-A/B, B-CD01A→B-CD01, "B- GF01"→B-GF01). Dev-DB: 60 ISLTH-vakken → 29 met meerdere onderdelen, 31 met één (incl. 8 keuzevakken die de standaard houden), 0 overgeslagen. **Bugfix onderweg:** de weging-weergave in `cijfers/invoer` gebruikte `rtrim(number_format(w*100,0),'0')` en streek betekenisvolle nullen weg (40% → "4%", 100% → "1%"); viel niet op toen alles 100% was. Nu met 1 decimaal geformatteerd. **Niet in bron:** de guide-modules zonder curriculum-tegenhanger (B-HD03/04, B-QR10/11, B-AR09–12) en de 100-puntenschaal/bonus (Arabisch V+) — die schaal wordt intern niet ondersteund (OER-punt). PMGV/MGV/PABO volgen met hun eigen studiegids. 6 tests (`ToetsopbouwTest`); 491 groen. Handleiding (technisch) bijgewerkt en hergenereerd. |
| 2026-07-11 | **Studiegids-analyse + aanbevolen fixes (opdrachtgever 2026-07-11).** De studiegids BA Islamitische Theologie 2025-2026 vergeleken met de cijfer-/EC-/aanwezigheidslogica. Doorgevoerd (OER-afhankelijke normen bewust INSTELBAAR gemaakt, niet zelf ingevuld): (1) **Aanwezigheidsnorm 80% → 75%** (studiegids §2.3.3; `config/sis.php` `presentie.norm`, nu env-overschrijfbaar `SIS_PRESENTIE_NORM`). (2) **EC-model instelbaar**: `opleidingen.ec_model` (`knockout`|`compensatorisch`, nullable) + terugval `config('sis.cijfers.ec_model')` (default `knockout` = bestaand gedrag); `EcBerekening::bepaalEc(...,$model)` met een compensatorische tak (gewogen eindcijfer ≥ cesuur i.p.v. knock-out per onderdeel), model via `Cijferberekening::ecModel($vak)`, ook in `Overgangsbeoordeling`. Beheer stelt het per opleiding in via Opzoektabellen (generieke `select` kreeg een lege-waarde-optie). Achtergrond: de studiegids beschrijft gewogen toetsformules (wijst op compensatie), maar de bindende regel staat in het OER. (3) **Aanwezigheidssignaal bij cijfers**: de cijferinvoer toont een **Aanwezigheid**-kolom + waarschuwing bij wie onder de norm zit (studiegids: dan geen toetsdeelname) — niet blokkerend, docent/examencommissie wegen het mee. (4) **Cijferschaal-validatie uit config** (`schaal_min/max`, env `SIS_CIJFER_MIN/MAX`) i.p.v. hardcoded `between:1,10`. **Nog OPEN (OER/opdrachtgever, gedocumenteerd, niet verzonnen):** definitieve keuze EC-model per opleiding; 0–100-schaal + bonuspunten van sommige modules; per-vak-aanwezigheidsnorm; maximumcijfer na herkansing; BSA-EC-drempels; harde blokkade bij te lage aanwezigheid; toetsopbouw/weging per module invoeren (nu één 'Tentamen' 100%); fraude/tentamenuitsluiting; instroom 4×/jaar (tussentijdse vaktoewijzing); PABO-curriculum + PMGV 50 EC. 5 tests (`EcModelTest`) + `PresentieTest` bijgewerkt (norm 75); 485 groen. Handleidingen bijgewerkt en hergenereerd. |
| 2026-07-11 | **Multi-rol per gebruiker + gebruiker aanmaken (opdrachtgever 2026-07-11).** Een gebruiker kan naast een **primaire rol** (`users.rol`) nu **extra rollen** hebben; sommige collega's dragen meerdere petten. Nieuwe tabel `roltoewijzingen` (één regel per extra rol, uniek op `(user_id, rol)`, cascade). De rechten zijn de **unie** over alle rollen: `User::alleRollen()` levert de rolset, `User::magVolgensRol()` is waar zodra één rol de regel toestaat; álle `mag…()`-delegates op `User`, de `rol:`-middleware (nu `User::rolSleutels()`) en de Gates in `AutorisatieServiceProvider` gaan via die unie. De **`Rol`-enum blijft ongewijzigd** de bron van waarheid per rol (RolTest groen). **Scoping = ruimste rol wint:** `isOpleidingBeperkt()`/`isCursusBeperkt()`/`isRelatieBeperkt()` geven alleen `true` als géén andere rol brede inzage geeft (nieuwe helper `Rol::zietAlleOpleidingen()`). **Startdashboard** volgt de primaire rol (DashboardController ongewijzigd); de **sidebar voegt de menu's van álle rollen samen** zodat elk toegestaan scherm bereikbaar is (Bestuur houdt bewust zijn afgeschermde menu). **Gebruiker aanmaken** (er was geen mogelijkheid): `GebruikerController::store` (`POST /gebruikers`, `rol:beheerder`) met naam/e-mail/primaire rol/extra rollen/actief — **geen wachtwoord** (login via Entra ID/dev-login), aanmaak gelogd (`actie=aanmaak`, `veld=gebruiker`). Beheerscherm uitgebreid: aanmaakkaart, rolbadges tonen extra rollen, wijzigformulier met aanvinkbare extra rollen. **Risicocombinatie** Studentenzaken + cijferrol (Docent/Examencie/Directie) geeft een waarschuwing en wordt gelogd (niet geblokkeerd — soms nodig). Handleidingen (medewerkers + technisch) bijgewerkt en hergenereerd. 7 tests (`MultiRolTest`); 480 groen. |
| 2026-07-09 | **Presentieregistratie per college (verplicht voor de docent).** Genormaliseerd: tabel `presenties` = één regel per inschrijving × vak × onderwijsweek; nooit vaste weekkolommen. Keuze opdrachtgever: **8 weken per blok, één college per week**; norm **80%**, of **50%** bij de aanwezigheidsregeling. Docent voert per week 1 (aanwezig) of 0 (afwezig) in; een lege cel = nog niet geregistreerd en telt NIET als afwezigheid (anders wordt nalatigheid van de docent op de student afgewenteld). Een week geldt pas als geregistreerd wanneer álle presentieplichtige deelnemers een waarde hebben. **Vrijgestelde** studenten volgen het vak niet: geen invoer, server-side overgeslagen. De docent ziet op de lijst het label **50%** achter de naam en de geldende norm per student. Rolscheiding: registreren alleen docent van het eigen vak (Gate `presentie-registreren`); inzage docent/examencommissie/directie (eigen opleiding)/bestuur (Gate `presentie-inzien`); Studentenzaken, Financiën en Beheer hebben GEEN presentie-inzage — aanwezigheid is onderwijsinhoudelijke procesinformatie. Inzage en mutatie gelogd. Statistiek (gemiddelde aanwezigheid, verdeling 0–50/50–80/80–100%, per opleiding resp. per vak) op de dashboards van docent, directie en bestuur, opleidinggebonden gefilterd. UI-term is "Aanwezigheid"; *presentielijst* blijft de tentamenlijst met handtekening. |

---

## AVG-grenzen (hard)

- **Geen echte persoonsgegevens in ontwikkeling** — bouwen en testen uitsluitend
  op synthetische data.
- **Synthetische data alleen** — nooit productiedata committen, in geen enkele fase.
- **BSN** — pas toevoegen na expliciet akkoord (mogelijk pas bij DUO-processen).
- **Migratie van echte data** — uitsluitend in de laatste fase, onder toezicht van
  de Functionaris Gegevensbescherming (FG).
- **Regel voor deze repo:** er komt NOOIT een productiedatabase of echte
  persoonsgegevens in Git. De aanwezige `IUTSTD-*.mdb` (oude Access-database)
  wordt door `.gitignore` structureel buiten Git gehouden en niet gecommit.

---

## Aandachtspunten voor volgende sessie

- **Toolchain staat en draait.** PHP 8.3.32 (`%USERPROFILE%\php\8.3`), Composer
  (`%USERPROFILE%\bin`) en portable MariaDB 11.4.9 (poort 3307) zijn geïnstalleerd
  en op het user-PATH. Start alles met `.\scripts\dev.ps1`. Volledige uitleg en
  het lokaal↔intranet-verhaal staan in **docs/ONTWIKKELOMGEVING.md**.
- **OneDrive-quirk:** de repo staat in OneDrive; PHP's `is_writable()` ziet mappen
  daar ten onrechte als niet-schrijfbaar (ReadOnly-attribuut). `scripts\dev.ps1`
  haalt dat bit weg. Advies: projectmap naar een pad buiten OneDrive verplaatsen
  (bijv. `C:\dev\iuasr-sis`) voor stabiel ontwikkelen.
- **Design system is leidend uit `IUASR/iuasr-sis`.** De overige mappen onder
  `IUASR/` (homepage, aanmeldportaal, pages) horen bij een andere/ publieke site
  en zijn NIET leidend voor het interne SIS. De 23 genummerde schermen in
  `IUASR/iuasr-sis/*.html` zijn het referentiepunt voor Fase 3/4.
- **Openstaande parameters blokkeren nog schermen:** studentnummerlengte (5 of 6),
  voldoende-grens en EC-drempel per opleiding. Deze staan als `null` in code
  (config + kolommen) en moeten met de opdrachtgever worden bevestigd vóór
  cijfer-/EC-schermen (Fase 4).
- **Oude Access-database `IUTSTD-*.mdb`** — indien aanwezig in de map: bevat
  vermoedelijk echte persoonsgegevens; buiten de repo houden (al door `.gitignore`
  gedekt), onder toezicht FG.
- **Nog te doen in Fase 0/1:** DPIA-opzet en FO-document + datamodel-diagram in `docs/`.
