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
  </ul>

  <h2>7. Regulier onderhoud</h2>
  <ul>
    <li><b>Migraties:</b> <code>php artisan migrate</code> na een update (reversible; maak eerst een back-up).</li>
    <li><b>Cache:</b> <code>php artisan optimize:clear</code> bij onverwacht gedrag na wijzigingen.</li>
    <li><b>Logs:</b> <code>storage/logs/</code> (zitten niet in de back-up).</li>
    <li><b>Tests:</b> <code>php artisan test</code> moet groen zijn vóór uitrol.</li>
  </ul>

  <div class="tip">Deze handleiding wordt bijgewerkt zodra er functies of infrastructuur wijzigen. Controleer de datum onderaan op actualiteit.</div>
</body>
</html>
