# PROGRESS.md — IUASR Intern Studentbeheersysteem (SIS)

Continuiteitsbestand tussen sessies. Werk dit bij aan het einde van elke sessie.
Bouw per fase; ga nooit een fase vooruit zonder akkoord van de opdrachtgever.

---

## Projectstatus

- **Huidige fase:** Fase 5 afgerond; aanwezigheids- en collegegeldtermijnmodule
  opgeleverd (buiten de oorspronkelijke fasering, op verzoek van de opdrachtgever)
- **Laatst bijgewerkt:** 2026-07-10
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
  - [ ] **Fase B — Cursussen & cursisten.** Cursusbeheer (Arabische Taal € 265,
    Hifz € 330, Certificaat/Ijaaza € 430; uitbreidbaar), cursisten handmatig +
    Excel-bulkimport, inschrijvingen. Rollen Cursusadministratie + Cursusdirecteur
    toevoegen; Cursussen-module op actief.
  - [ ] **Fase C — Cursusgelden & boekhouding.** Betalingen registreren,
    betaalstatus/openstaand, methoden (iDEAL/overboeking/contant), historie.
  - [ ] **Fase D — Cursusdirecteuren.** Koppeling cursus↔directeur, toegang
    beperkt tot eigen cursus(sen).
  - [ ] **Fase E — Rapporten & dashboards** voor Schoolbestuur en directeuren.
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
| 2026-07-09 | **Directie per opleiding (opleidinggebonden zichtbaarheid).** Nieuwe koppeltabel `directie_opleidingen` (user ↔ opleiding, echte FK's, cascade). Een directielid ziet uitsluitend studenten, cijfers en rapporten van de toegewezen opleiding(en); zonder toewijzing niets (need-to-know). Toegepast op studentenlijst/-dossier, cijferoverzicht, cijferlijst, EC-rapport, overgang, alumni, resultaten-mailen én de dashboardstatistieken (per-opleiding gefilterd) + KPI-tegels. Beheer wijst toe via **Gebruikers & rollen → Directie — opleidingtoewijzing**. Seed: PABO-directeur (PABO), GV-directeur (PMGV+MGV), Theologie-directeur (ISLTH+cursussen). Een **dubbel ingeschreven** student is zichtbaar voor de directie van elke opleiding waarin hij/zij actief is. Helpers `User::opleidingIds()`, `Student::scopeZichtbaarVoor()`/`zichtbaarVoor()`. |
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
