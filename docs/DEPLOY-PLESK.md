# Deploy naar Plesk (subdomein) — synthetische demo

Deze handleiding zet de **huidige versie** (uitsluitend synthetische data, nog
geen echte gebruikers) neer op een **Plesk-subdomein**, via **Git-pull vanaf
GitHub**, **zonder SSH** (alles via het Plesk-paneel). De lokale
ontwikkelomgeving blijft ongewijzigd: het is dezelfde code met per omgeving een
eigen `.env` (zie `docs/ONTWIKKELOMGEVING.md`).

## Belangrijk vooraf (lees dit eerst)

- **Alleen synthetische data.** Nooit echte persoonsgegevens op een publieke
  Plesk-server (AVG-hard, zie `CLAUDE.md`). Echte data hoort pas op de interne
  intranetserver, in Fase 7, onder de Functionaris Gegevensbescherming.
- **`APP_ENV=local` (niet `production`).** De tijdelijke dev-login werkt alleen
  in `local`/`testing` (`app/Http/Controllers/Auth/DevLoginController.php`).
  Met `production` kun je niet inloggen zolang Entra SSO nog niet gekoppeld is.
  Zet daarom `APP_ENV=local` **maar** `APP_DEBUG=false`.
- **Scherm het subdomein af.** De dev-login laat iedereen elke rol kiezen en de
  IP-allowlist wordt nog niet door code afgedwongen. Beveilig het subdomein op
  Plesk-niveau met een wachtwoord (stap 8). Doe dit vóór je gaat migreren.
- **HTTPS aan.** Gebruik het gratis Let's Encrypt-certificaat van Plesk.

## Aanpak in het kort

1. Subdomein + SSL + PHP 8.2/8.3 instellen
2. MySQL-database aanmaken in Plesk
3. Code via Git pullen vanaf GitHub (repo is publiek → geen deploy-key nodig)
4. Document root op `/public` zetten
5. `composer install --no-dev` draaien (Plesk Composer-extensie of SFTP-fallback)
6. `.env` aanmaken (template hieronder, met eigen DB-gegevens en APP_KEY)
7. `php artisan migrate --seed` + `storage:link` draaien (Plesk Scheduled Tasks)
8. Subdomein met wachtwoord beveiligen

---

## Stap 1 — Subdomein, SSL en PHP-versie

1. **Websites & Domains → Add Subdomain**, bv. `sis.jouwdomein.nl`.
2. **SSL/TLS Certificates → Install** een gratis **Let's Encrypt**-certificaat
   voor het subdomein. Zet "Redirect from HTTP to HTTPS" aan.
3. **PHP Settings** van het subdomein → kies **PHP 8.3** (of 8.2). Controleer dat
   deze extensies aan staan: `pdo_mysql, mbstring, openssl, ctype, fileinfo,
   tokenizer, xml, curl, zip, gd, intl`. Zet `memory_limit` ≥ 256M.

## Stap 2 — MySQL-database

**Databases → Add Database** (op het subdomein):
- Databasenaam: bv. `sis_demo`
- Nieuwe databasegebruiker + sterk wachtwoord (noteren voor `.env`)
- Host op de server is `127.0.0.1`, poort **3306** (lokaal was dat 3307 — op de
  server standaard 3306).

## Stap 3 — Git-deploy vanaf GitHub

**Websites & Domains → (subdomein) → Git**:
- **Remote Git repository:** `https://github.com/eraduz/iuasr-sis.git`
- De repo is **publiek**, dus je hoeft de door Plesk getoonde deploy-key
  nergens toe te voegen.
- **Branch:** `main`
- **Deployment mode:** begin met **Manual** (later eventueel "automatic on push").
- **Server path / deploy-map:** laat Plesk in de subdomeinmap clonen
  (bv. `httpdocs`). We verleggen daarna de document root naar de `public`-submap.
- Klik **Deploy** / **Pull**. De projectbestanden staan nu op de server
  (zonder `vendor/` — die installeer je in stap 5).

## Stap 4 — Document root op `/public`

Laravel serveert vanuit `public/`, niet vanuit de projectroot.

**Websites & Domains → (subdomein) → Hosting Settings → Document root:**
zet deze op `<deploy-map>/public` (bv. `httpdocs/public`).

> Zo staan `.env`, `vendor/`, `storage/` e.d. buiten de webroot en zijn ze niet
> rechtstreeks opvraagbaar. De `public/.htaccess` uit Laravel regelt de routing.

## Stap 5 — Composer install (zonder SSH)

`vendor/` staat niet in Git en moet op de server geïnstalleerd worden.

**Voorkeursweg — Plesk Composer-extensie:**
1. (Eenmalig, als admin) installeer de gratis extensie **"PHP Composer"** uit de
   Plesk Extensions-catalogus.
2. Op het subdomein verschijnt een **Composer**-knop. Open die, kies de map met
   `composer.json`, en draai **Install** met opties
   `--no-dev --optimize-autoloader`.

**Fallback zonder extensie/SSH — vendor uploaden via SFTP:**
1. Lokaal een schone productie-`vendor/` maken:
   `composer install --no-dev --optimize-autoloader`
2. De map `vendor/` via **File Manager / SFTP** naar de deploy-map uploaden.
   (Werkt omdat PHP-packages platform-onafhankelijk zijn.)

## Stap 6 — `.env` aanmaken

Maak via **File Manager** een bestand `.env` in de deploy-map (naast
`composer.json`) met onderstaande inhoud. Vul je eigen DB-gegevens in en gebruik
de meegeleverde `APP_KEY` (of genereer een nieuwe in stap 7).

```env
APP_NAME="IUASR SIS"
APP_ENV=local
APP_KEY=base64:VERVANG_DOOR_EIGEN_SLEUTEL
APP_DEBUG=false
APP_URL=https://sis.jouwdomein.nl

APP_LOCALE=nl
APP_FALLBACK_LOCALE=nl
APP_FAKER_LOCALE=nl_NL

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sis_demo
DB_USERNAME=VUL_IN
DB_PASSWORD=VUL_IN

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true

CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

MAIL_MAILER=log

# IP-allowlist (nog niet door code afgedwongen; afscherming via stap 8)
SIS_TOEGESTANE_IPS=
```

> `APP_ENV=local` is bewust (dev-login), `APP_DEBUG=false` is bewust (geen
> foutdetails lekken). `SESSION_SECURE_COOKIE=true` omdat het subdomein op HTTPS
> draait.

## Stap 7 — Migreren, seeden en storage:link (Scheduled Tasks)

Zonder SSH draai je artisan-commando's via **Tools & Settings → Scheduled Tasks**
(of **Websites & Domains → Scheduled Tasks**). Voeg telkens een taak toe van type
**"Run a command"**, laat 'm **één keer** lopen (Run Now), en verwijder/deactiveer
'm daarna. Werkdir = de deploy-map; gebruik het volledige pad naar de Plesk-PHP
(pas het PHP-versienummer aan):

```bash
cd /var/www/vhosts/jouwdomein.nl/sis.jouwdomein.nl && /opt/plesk/php/8.3/bin/php artisan key:generate --force
cd /var/www/vhosts/jouwdomein.nl/sis.jouwdomein.nl && /opt/plesk/php/8.3/bin/php artisan migrate --seed --force
cd /var/www/vhosts/jouwdomein.nl/sis.jouwdomein.nl && /opt/plesk/php/8.3/bin/php artisan storage:link
cd /var/www/vhosts/jouwdomein.nl/sis.jouwdomein.nl && /opt/plesk/php/8.3/bin/php artisan config:cache
```

- `key:generate --force` mag je overslaan als je de `APP_KEY` al in `.env` zette.
- `migrate --seed` laadt het schema + **synthetische** data (35 accounts, ~94
  studenten). Draai dit maar één keer.
- Kun je geen scheduled task draaien, dan werkt de **Git "additional deployment
  actions"** vaak ook (zelfde commando's, zonder de `cd`).

## Stap 8 — Subdomein afschermen (verplicht)

Omdat er nog geen echte login is:

**Websites & Domains → (subdomein) → Password-Protected Directories** → bescherm
`/` met een gebruikersnaam + wachtwoord. Iedereen krijgt dan eerst een
browser-wachtwoordvenster vóór de app. (Alternatief/aanvullend: op een dedicated
server een nginx `allow/deny` op je kantoor-IP.)

Test daarna: `https://sis.jouwdomein.nl` → wachtwoordvenster → login-pagina met
de rolkiezer.

---

## Later bijwerken (na lokale wijzigingen)

1. Lokaal committen en pushen naar GitHub (`git push`).
2. In Plesk **Git → Pull/Deploy**.
3. Composer opnieuw (alleen bij gewijzigde dependencies).
4. Scheduled task: `php artisan migrate --force` (nieuwe migraties) en
   `php artisan config:cache`. Gebruik **`migrate`**, nooit `migrate:fresh` als je
   demodata wilt behouden (zie `TESTEN.md`).

## Troubleshooting

- **HTTP 500 / witte pagina:** rechten op `storage/` en `bootstrap/cache/`
  (moeten schrijfbaar zijn voor de webserver). Log staat in
  `storage/logs/laravel.log`. Zet tijdelijk `APP_DEBUG=true` om de fout te zien,
  daarna weer uit.
- **"No application encryption key":** `APP_KEY` ontbreekt → stap 6/7.
- **Login-pagina toont geen accounts / 404 op inloggen:** `APP_ENV` staat op
  `production`. Zet 'm op `local` en draai `php artisan config:cache` opnieuw.
- **Redirect-lus of assets over http op een https-pagina:** Laravel zit achter
  de nginx-proxy van Plesk. Voeg in `bootstrap/app.php` bij `->withMiddleware`
  toe: `$middleware->trustProxies(at: '*');`.
- **404 op alle routes behalve de homepage:** document root wijst niet naar
  `/public`, of `public/.htaccess`/mod_rewrite ontbreekt.

## Waarom de lokale versie blijft werken

Je lokale omgeving en Plesk delen alleen de **code** (via Git). De **database**
en de **`.env`** zijn per omgeving apart: lokaal MariaDB op poort 3307, op Plesk
MySQL op 3306. `.env` staat in `.gitignore` en reist dus nooit mee. Je kunt
lokaal blijven ontwikkelen en testen zonder de Plesk-omgeving te raken.
```
