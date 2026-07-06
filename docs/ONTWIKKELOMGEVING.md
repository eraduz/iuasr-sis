# Ontwikkelomgeving — IUASR SIS

Dit document beschrijft de lokale ontwikkelopstelling en — belangrijk — hoe de
lokale omgeving zich verhoudt tot de latere **intranetserver**. Alles hier draait
op **synthetische data**; er komt nooit een productiedatabase of echt
persoonsgegeven in deze omgeving of in Git.

## Wat er lokaal geïnstalleerd is

| Onderdeel | Versie | Locatie | Bijzonderheid |
|-----------|--------|---------|---------------|
| PHP (CLI, NTS x64) | 8.3.32 | `%USERPROFILE%\php\8.3` | op user-PATH; `php.ini` met o.a. `pdo_mysql`, `openssl`, `mbstring` |
| Composer | 2.10.x | `%USERPROFILE%\bin\composer.bat` | shim rond `composer.phar` |
| MariaDB (portable) | 11.4.9 LTS | `%USERPROFILE%\mariadb\...` | MySQL-compatibel; **poort 3307**; geen Windows-service |
| Database-data | — | `%USERPROFILE%\mariadb-data` | buiten de repo; nooit in Git |

Geen van deze installaties vereiste adminrechten; alles staat in het
gebruikersprofiel.

## Dagelijks starten

Vanuit de projectmap in PowerShell:

```powershell
.\scripts\dev.ps1
```

Dit zet PHP/Composer op het pad, start MariaDB (3307) en start de Laravel-server
op <http://127.0.0.1:8000>. Losse scripts: `scripts\db-start.ps1`,
`scripts\db-stop.ps1`.

Eerste keer opzetten (of na `git clone`):

```powershell
composer install
copy .env.example .env      # en DB-gegevens invullen (zie hieronder)
php artisan key:generate
php artisan migrate --seed   # laadt UITSLUITEND synthetische data
```

## Poorten — "geen conflicten"

- **Laravel-webserver:** `127.0.0.1:8000` (alleen lokaal).
- **MariaDB:** `127.0.0.1:3307` — bewust **niet** 3306. Zo botst het niet met een
  eventueel al aanwezige MySQL/MariaDB (die standaard 3306 gebruikt), en niet met
  de latere productie-MySQL. De poort staat in `.env` (`DB_PORT=3307`) en in
  `%USERPROFILE%\mariadb\my.ini`.
- MariaDB luistert alleen op `127.0.0.1` (`bind-address`), dus niet bereikbaar
  van buiten deze machine.

Omdat de database in een **apart gebruikersprofiel-pad** draait en niet als
service is geïnstalleerd, start je hem expliciet wanneer je hem nodig hebt en
raakt hij niets anders op de machine.

## Bekende Windows/OneDrive-eigenaardigheid

De repo staat in een OneDrive-map. OneDrive zet het **ReadOnly-attribuut** op
mappen. PHP's `is_writable()` op Windows leest dat ten onrechte als "niet
schrijfbaar", terwijl schrijven wél werkt. Laravel weigert dan te starten met:
*"bootstrap/cache directory must be present and writable"*.

Oplossing (zit al in `scripts\dev.ps1`):

```powershell
attrib -R "bootstrap\cache" /D
attrib -R "storage" /S /D
```

OneDrive kan het bit opnieuw zetten; `dev.ps1` haalt het daarom bij elke start
weg. **Advies voor serieuze ontwikkeling:** zet de projectmap buiten OneDrive
(bijv. `C:\dev\iuasr-sis`). Dat voorkomt deze quirk én sync-conflicten op
`vendor/` en tijdelijke bestanden.

## Lokaal versus de intranetserver (productie)

Dit is de kern van "hoe zit het met de server die alleen via intranet
bereikbaar is". Het idee: **dezelfde code, per omgeving een andere `.env` en een
andere database.** De database zelf reist nooit mee — die wordt overal opnieuw
opgebouwd uit de migraties.

| | Lokaal (ontwikkeling) | Intranetserver (productie) |
|---|---|---|
| Webserver | `php artisan serve` (dev) | Nginx/Apache of IIS + PHP-FPM |
| Database | portable MariaDB, poort 3307, synthetisch | MySQL/InnoDB op de server, echte data (pas na Fase 7, onder FG) |
| Bereikbaar | alleen `127.0.0.1` | alleen via intranet, **IP-beperkt** (`SIS_TOEGESTANE_IPS`) |
| Auth | SSO-scherm (nog niet gekoppeld) | Microsoft Entra ID (SSO/OIDC) |
| `.env` | `APP_ENV=local`, `APP_DEBUG=true` | `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` uniek |
| Data | seeders (synthetisch) | migraties draaien; migratie oude data pas in Fase 7 |

Waarom dit conflictvrij is:

1. **Schema = migraties, data = seeders.** Geen binair databasebestand in Git.
   `php artisan migrate` bouwt hetzelfde schema op elke machine. De lokale en de
   productiedatabase zijn dus volledig gescheiden; je kunt lokaal vrij testen
   zonder ooit de intranetdatabase te raken.
2. **`.env` is per omgeving en staat niet in Git** (staat in `.gitignore`). De
   `APP_KEY` verschilt per omgeving; dat is ook de sleutel waarmee het BSN wordt
   versleuteld — die hoort dus nooit gedeeld of gecommit te worden.
3. **MariaDB lokaal ≈ MySQL productie.** Laravel praat via dezelfde `mysql`-driver
   met beide; voor ons gebruik (InnoDB, foreign keys, utf8mb4) zijn ze
   uitwisselbaar. Wil je exact gelijk draaien, dan kan lokaal ook MySQL i.p.v.
   MariaDB — de stappen zijn identiek, alleen een ander binair pakket.

### Naar de intranetserver (later, apart opleverpunt)

Kort stappenplan voor go-live (details in het Technisch Ontwerp, Fase 2/3):

1. On-prem server/VM op het intranet, PHP-FPM + webserver, MySQL/InnoDB.
2. Code uitrollen (git of artefact), `composer install --no-dev --optimize-autoloader`.
3. `.env` met `APP_ENV=production`, eigen `APP_KEY`, DB-gegevens van de server,
   `SIS_TOEGESTANE_IPS` gevuld met de toegestane intranet-IP's/CIDR.
4. `php artisan migrate --force` (nog geen echte data), daarna Entra-koppeling.
5. Firewall/webserver zo dat de app **alleen** via het intranet en toegestane
   IP's bereikbaar is. HTTPS met een interne certificaatautoriteit.
6. Back-up van de database inrichten (geautomatiseerd, getest herstelbaar,
   buiten de app-machine) — zie PvA §11.
7. Echte data-migratie pas in **Fase 7**, onder toezicht van de Functionaris
   Gegevensbescherming.

## Waarom er geen databasebestand in Git staat

Dit is een harde AVG-regel van het project (zie `CLAUDE.md` en `PROGRESS.md`) en
tegelijk goede praktijk: het oude systeem was juist één los Access-bestand. In het
nieuwe systeem is de database reproduceerbaar uit versiebeheerde migraties en
synthetische seeders. Een dump of `.sqlite`/`.mdb`/`.sql` committen zou echte of
per ongeluk gevoelige data in de geschiedenis kunnen vastleggen; `.gitignore`
blokkeert die bestandstypen daarom expliciet.
