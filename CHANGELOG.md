# Changelog — IUASR Management Systeem

Alle noemenswaardige wijzigingen staan hier, nieuwste bovenaan. Het versienummer
staat onderaan elke pagina (`config/sis.php` → `sis.versie`) en volgt semantische
versienummering: **MAJOR.MINOR.PATCH**. De uitgebreide, technische geschiedenis
staat in `PROGRESS.md`.

Werkwijze bij een release: verhoog `sis.versie`, voeg hieronder een kort blok toe
(PATCH = bugfixes, MINOR = nieuwe functies, MAJOR = ingrijpende wijzigingen) en
noem de datum.

## [1.0.1] — 2026-07-13 — Webhook werkt

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
