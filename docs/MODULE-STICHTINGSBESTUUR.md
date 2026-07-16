# Module Stichtingsbestuur

Ontwerp van de module voor het bijhouden van het bestuur en het toezicht van de
stichting. Opdrachtgever, 2026-07-16 (`Documents/nieuwe module met nieuwe role
stichtingsbestuur.txt`).

## Doel en scope

Het **Stichtingsbestuur** en de **Raad van Toezicht** van de stichting bijhouden —
de leden/commissarissen én de (jaarlijkse) vergaderingen met onderwerpen, besluiten
en aanwezigheid. Bewust een **afgeschermde** module: het gaat om governance- en
persoonsgegevens.

## Rol

Eén nieuwe rol **`Rol::Stichtingsbestuur`** beheert de module; de **Beheerder** voor
onderhoud. **Geen meekijkers** — anders dan de andere modules kijkt hier bewust geen
enkele andere rol mee. Deze rol staat los van het bestaande **Schoolbestuur**
(`Rol::Bestuur`), dat de onderwijs-/modulestatistieken overziet.

## Datamodel

- **`bestuursleden`** — leden van beide organen, onderscheiden via `orgaan`
  (`stichtingsbestuur` | `raad_van_toezicht`). Velden: `titel`, `voornaam`,
  `achternaam`, `geboortedatum`, `adres`, `telefoon`, `email`, `datum_in_functie`,
  `datum_uit_functie`, `bevoegdheid` (alleen voor het bestuur), `actief`. Een
  afgetreden lid blijft bewaard (`actief = false` + `datum_uit_functie`) — historie.
- **`bestuursvergaderingen`** — `datum`, `orgaan` (soort), `locatie`, `onderwerpen`,
  `besluiten`, `opmerking`, `genotuleerd_door_id`.
- **`bestuursvergadering_aanwezigheden`** — één rij per (vergadering, lid) met
  `aanwezigheid` (`fysiek` | `online` | `niet_bijgewoond`); uniek op (vergadering, lid).

## Enums

- `Bestuursorgaan` — Stichtingsbestuur / Raad van Toezicht (lid én vergadering).
- `Bestuurstitel` — voorzitter, penningmeester, secretaris, lid, commissaris; met
  `isDagelijksBestuur()` (voorzitter/penningmeester/secretaris).
- `Aanwezigheid` — fysiek / online / niet bijgewoond; met `badge()` en `isAanwezig()`.

## Autorisatie

`Rol::magStichtingsbestuurBeheren()` / `magStichtingsbestuurInzien()` (Stichtingsbestuur,
Beheerder), Gates `stichtingsbestuur-inzien`/`-beheren`, routegroep
`rol:stichtingsbestuur,beheerder`. Alle mutaties gelogd via `AuditLogger`
(persoonsgegevens).

## Schermen

- **Overzicht** — kerncijfers + samenstelling (bestuur / RvT) + recente vergaderingen.
- **Leden & RvT** — lijst met filter op orgaan/actief; toevoegen, bewerken, verwijderen.
- **Vergaderingen** — lijst; detail met onderwerpen, besluiten en aanwezigheid; een
  formulier met een **aanwezigheidsraster** per actief lid (fysiek/online/niet/leeg).

## Tests

`tests/Feature/StichtingsbestuurModuleTest.php` — keuzescherm, rolscheiding,
lid toevoegen (gelogd), commissaris zonder bevoegdheid, vergadering met aanwezigheid,
lege aanwezigheid verwijdert de registratie, detail rendert. 8 tests.
