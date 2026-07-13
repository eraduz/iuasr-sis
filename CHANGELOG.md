# Changelog — IUASR Management Systeem

Alle noemenswaardige wijzigingen staan hier, nieuwste bovenaan. Het versienummer
staat onderaan elke pagina (`config/sis.php` → `sis.versie`) en volgt semantische
versienummering: **MAJOR.MINOR.PATCH**. De uitgebreide, technische geschiedenis
staat in `PROGRESS.md`.

Werkwijze bij een release: verhoog `sis.versie`, voeg hieronder een kort blok toe
(PATCH = bugfixes, MINOR = nieuwe functies, MAJOR = ingrijpende wijzigingen) en
noem de datum.

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
- **Rolscheiding**: de Balie registreert en wijzigt; Directie en Bestuur lezen mee
  (alleen-lezen). De rol is bewust smal — geen studentdossiers, cijfers of
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
