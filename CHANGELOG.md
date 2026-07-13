# Changelog — IUASR Management Systeem

Alle noemenswaardige wijzigingen staan hier, nieuwste bovenaan. Het versienummer
staat onderaan elke pagina (`config/sis.php` → `sis.versie`) en volgt semantische
versienummering: **MAJOR.MINOR.PATCH**. De uitgebreide, technische geschiedenis
staat in `PROGRESS.md`.

Werkwijze bij een release: verhoog `sis.versie`, voeg hieronder een kort blok toe
(PATCH = bugfixes, MINOR = nieuwe functies, MAJOR = ingrijpende wijzigingen) en
noem de datum.

## [1.3.0] — 2026-07-13

Import van de bestaande Excel-bibliotheek, en zelf-filterende filterbalken.

- **Importwizard** (`bibliotheek:importeren` + scherm onder Bibliotheek → Importeren).
  Eerst proefdraaien (inlezen, rapporteren, niets opslaan), dan pas importeren.
  Idempotent: een tweede import maakt niets dubbel.
- **Normalisatie van de bron**, die met de hand is gegroeid: het vakgebied komt uit
  de **rekletter** (A = Tafsir, F = Fiqh, …) in plaats van uit de vakgebiedkolom met
  144 spellingvarianten; taalfouten worden gecorrigeerd (Arabish → Arabisch,
  Nederlans → Nederlands); waarden die geen taal zijn (woordenboeken, Grammatica)
  verhuizen naar de opmerking. Frans, Duits, Spaans en Albanees toegevoegd als taal.
  De oorspronkelijke bronwaarden blijven bewaard in het opmerkingveld.
- **Aantal = exemplaren**: "F. 143" met aantal 3 wordt één titel met F.143-1, F.143-2
  en F.143-3, in kast F. Een onwaarschijnlijk aantal (de bron bevat één keer 41306)
  wordt teruggebracht tot 1 exemplaar en gemeld.
- **Streaming XLSX-lezer** in plaats van het hele bestand in het geheugen laden;
  12.470 regels paste niet in 128 MB.
- Resultaat op de dev-database: **10.969 titels, 15.928 exemplaren, 7.795 auteurs**,
  geen enkele titel zonder vakgebied. 188 bronregels overgeslagen (geen titel).
- **Filterbalken filteren nu vanzelf**: een keuzelijst of datum aanpassen verzendt
  het formulier direct; het zoekveld blijft op Enter werken. Met een regel die de
  actieve filters toont.

## [1.2.0] — 2026-07-13

Nieuwe module **Bibliotheek** (op verzoek van de opdrachtgever), met een nieuwe
rol `bibliotheek`.

- **Titel en exemplaar gescheiden** (model van Koha e.a.): de titel staat één keer
  in de catalogus, de fysieke boeken hangen eronder als exemplaren met een eigen
  serienummer, kastplek en status. Drie exemplaren van hetzelfde boek zijn dus los
  uitleenbaar zonder de titel te dupliceren.
- **Meertalig**: Arabisch, Turks, Engels en Nederlands. Een publicatie kan meerdere
  talen hebben; opslaan, zoeken en sorteren werkt met Arabisch schrift.
- **Boekreeksen**: de gedeelde gegevens één keer invoeren en alle delen in één
  scherm toevoegen (Tafsir Ibn Kathir deel 1–4). Elk deel blijft los uitleenbaar.
- **Tijdschriften met artikelen**: uitgaven met artikelen, auteurs, pagina's en
  trefwoorden. Zoeken op artikeltitel, auteur, trefwoord of tijdschriftnaam laat
  meteen zien in welk tijdschrift een artikel staat.
- **Uitlenen en innemen**: de lener is een bestaande student of medewerker (echte
  foreign key). Inname legt de staat van het materiaal vast; bij schade gaat het
  exemplaar automatisch uit de uitleen. 'Te laat' en 'op tijd' zijn afleidingen,
  geen kolommen.
- **E-mail**: vijf sjablonen (door de Beheerder aanpasbaar) met variabelen, altijd
  met CC naar de bibliotheekpostbus. Elke verzending wordt gelogd, ook een mislukte;
  een mislukte mail blokkeert de uitlening nooit. Automatische herinneringen via de
  scheduler (`bibliotheek:herinneringen`, idempotent).
- **Dashboard en rapportage**: KPI's, waarschuwingen (te laat, binnen 3 dagen terug),
  grafieken per maand/vakgebied/taal, overzichten per vakgebied, auteur en jaar, de
  meest uitgeleende titels, en een CSV-export.
- **Integratie met Studentenzaken**: te late studenten verschijnen op het
  Studentenzaken-dashboard met studentnummer, naam, materiaal, dagen te laat en het
  aantal verstuurde waarschuwingen.
- **Boete nog niet gebouwd**: de boeteregels zijn niet vastgesteld; er worden geen
  bedragen verzonnen. De uitleentermijnen (21 dagen student, 60 docent) staan in
  `config/sis.php` en zijn TE BEVESTIGEN.

## [1.1.0] — 2026-07-13

Nieuwe module **Balie / Receptie** (op verzoek van de opdrachtgever), met een
nieuwe rol `balie`.

- **Eén chronologisch logboek** (`balie_registraties`) voor alle vijf de stromen
  aan de ingang: telefoon inkomend/uitgaand, bezoekers, en post ontvangen/verzonden.
  Discriminatoren `soort` + `richting`; geen vijf losse tabellen, zodat zoeken,
  filteren en exporteren één implementatie is.
- **Bezoekersregistratie met aankomst én vertrek.** "Nu in het pand" wordt afgeleid
  (bezoek zonder vertrekmoment), niet opgeslagen; bedoeld als ontruimingsoverzicht.
- **Echte foreign keys**: `medewerker_id` (bestemd voor / afspraak met) met
  `afdeling` als terugval, en `geregistreerd_door_user_id`.
- **Zoeken en filteren** over onderwerp, naam, organisatie, afdeling en toelichting;
  filters op soort, richting, medewerker, periode en "alleen nog aanwezig".
  **CSV-export** die dezelfde filters respecteert.
- **Rolscheiding**: de Balie registreert en wijzigt; alleen het Schoolbestuur leest
  mee (alleen-lezen). De Directie heeft geen toegang: het logboek is een
  werkregister van de balie, geen opleidingsinformatie. De rol is bewust smal — geen studentdossiers, cijfers of
  personeelsdossiers. Registraties worden nooit verwijderd; aanmaak, wijziging,
  afmelden en export worden gelogd.
- Migratie breidt de enum `users.rol` uit en maakt de modulerij plus het
  balie-account aan (idempotent), zodat een bestaande database alleen
  `php artisan migrate` nodig heeft.

## [1.0.1] — 2026-07-13

Configuratie per omgeving rechtgezet. Gedeelde instellingen staan nu in Git in
plaats van in `.env`, zodat een nieuwe omgeving (RDP, Plesk) ze met `git pull`
krijgt en niet meer per machine kan afwijken.

- **Tijdzone hersteld naar Europe/Amsterdam.** Laravel 12 legt `timezone` hard op
  `UTC` vast zolang er geen eigen `config/app.php` is; `APP_TIMEZONE` in `.env`
  werd daardoor genegeerd en de hele applicatie rekende in UTC. `config/app.php`
  is toegevoegd (tijdzone, taal `nl`, faker `nl_NL`).
- **Sessies worden nu versleuteld.** De framework-standaard `SESSION_ENCRYPT=false`
  won van de bedoelde instelling. `config/session.php` legt `encrypt` op `true`
  vast; `secure` blijft per omgeving instelbaar (https → `true`).
- **`.env.example` teruggebracht** tot uitsluitend machine-specifieke sleutels
  (URL, database, debug, cookie, IP-bereik, e-mail) plus de gedeelde `APP_KEY`.
  De dode sleutel `SIS_STUDENTNUMMER_CIJFERS` is vervangen door
  `SIS_STUDENTNUMMER_VOLGNUMMER_LENGTE`, die de code daadwerkelijk leest.

## [1.0.0] — 2026-07-13

Eerste vastgelegde versie. Het systeem is uitgegroeid van een studentbeheersysteem
tot een intern managementplatform met meerdere modules:

- **Studentenzaken** — studenten en inschrijvingen, lifecycle (in-/uit-/her­inschrijven,
  schorsen, afstuderen/alumni met afstudeerproces), documenten, verklaringen,
  collegegeld in termijnen, rapporten.
- **Cijfers** — genormaliseerde resultaten, vaststelling, cijferlijst/transcript,
  aanwezigheidsregistratie, EC- en overgangsrapporten (rolgescheiden).
- **Cursussen Administratie** — cursussen, cursisten, cursusgelden, rapportage.
- **Relatiebeheer & Stage** — organisaties, contactpersonen, stages, agenda,
  documenten en dashboards (opleidinggebonden).
- **HR / Personeelszaken** — medewerkers, dienstverbanden, verlof en verzuim,
  gesprekken, onboarding/offboarding, signaleringen (Wet Verbetering Poortwachter).
- **Platform** — rolgescheiden dashboards, multi-rol per gebruiker, een samengevoegd
  schoolbestuursoverzicht, donkere modus (standaard) en een HTML-handleiding met
  hoofdstuknavigatie.

Volledige details en de onderbouwing per beslissing: zie `PROGRESS.md`.
