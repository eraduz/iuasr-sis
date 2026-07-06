# IUASR — Intern Studentbeheersysteem (SIS)

Intern studentbeheersysteem voor Studentenzaken van IUASR. Vervangt een
verouderd Access/VBA-systeem. Draait op het interne netwerk (intranet),
strikt gescheiden van het publieke aanmeldportaal.

## Stack

- PHP + Laravel
- MySQL / InnoDB (echte foreign keys, surrogaatsleutels)
- Authenticatie via Microsoft Entra ID (SSO/OIDC)
- Intern, IP-beperkt

## Status

In opbouw — **Fase 2-aanzet (technische opzet)**: Laravel-projectskelet met
genormaliseerd datamodel, rolscheiding en het leidende design system. Zie
**[PROGRESS.md](PROGRESS.md)** voor de fasering, beslissingen en openstaande
parameters, en **[CLAUDE.md](CLAUDE.md)** voor de niet-onderhandelbare
principes en AVG-grenzen.

## Lokaal opzetten (ontwikkeling)

> PHP en Composer moeten geïnstalleerd zijn (PHP ^8.2). Node/npm zijn aanwezig.

```bash
composer install
cp .env.example .env
php artisan key:generate
# Zet DB-gegevens in .env (uitsluitend een lege ontwikkeldatabase).
php artisan migrate --seed   # laadt UITSLUITEND synthetische data
npm install && npm run dev
php artisan serve
```

Rol wisselen in de demo-shell kan via de rolwisselaar rechtsboven; de
rolscheiding zelf wordt server-side afgedwongen (Gates + `rol`-middleware).

## Belangrijke regels

- Bouwen per fase; niet vooruitlopen zonder akkoord.
- **Geen echte persoonsgegevens in ontwikkeling** — uitsluitend synthetische data.
- Er komt nooit een productiedatabase of echte persoonsgegevens in Git.
- Taal in UI en documentatie: Nederlands (U-vorm).

## Mappenstructuur

- `app/` — modellen, rollen (`Enums/Rol`), autorisatie (`Providers`, `Http/Middleware`), EC-logica (`Support`).
- `database/migrations/` — genormaliseerd InnoDB-schema met surrogaatsleutels en echte foreign keys.
- `database/seeders/` — uitsluitend synthetische seed-data.
- `resources/views/` — Blade-views gekoppeld aan het leidende design system.
- `public/assets/` — het overgenomen design system (`sis.css`, `iuasr-plugin-dash.css`, `sis-shell.js`).
- `docs/` — ontwerp- en projectdocumentatie (FO, TO, datamodel, AVG/DPIA).
- `synthetische-data/` — synthetische seed-datasets (nooit echte data).
- `IUASR/` — bronmateriaal en designs; **`IUASR/iuasr-sis/` is leidend** voor de SIS-schermen.
