# ============================================================================
# env-testing.ps1 - leidt .env.testing af van .env (per machine, NIET in Git).
#
# LET OP: dit script is bewust ASCII-only. PowerShell 5.1 leest een .ps1 zonder
# BOM als Windows-1252. Een UTF-8 em-streepje (E2 80 94) wordt dan gelezen als
# "a-euro-rechtsdubbelaanhalingsteken", en dat laatste teken behandelt PowerShell
# ALS AANHALINGSTEKEN. Een liggend streepje in een comment sloopt daarmee de hele
# parser ("The string is missing the terminator"). Gebruik hier dus geen accenten,
# em-streepjes of typografische aanhalingstekens.
#
# WAAROM DIT BESTAND MOET BESTAAN
# `php artisan <commando> --env=testing` laadt .env.testing. Bestaat dat niet,
# dan valt Laravel STILZWIJGEND terug op de gewone .env, en die wijst naar de
# ONTWIKKELdatabase. Zo is op 17-07-2026 de complete ontwikkeldatabase gewist
# met een `migrate:fresh --env=testing`. De <env>-regels in phpunit.xml helpen
# daar niet tegen: die gelden alleen onder phpunit, niet bij een los commando.
#
# WAAROM AFGELEID EN NIET IN GIT
# Een .env.testing met vaste inloggegevens in Git brak de RDP: die machine heeft
# een ander databasewachtwoord, en `php artisan serve` geeft APP_ENV door aan het
# webserverproces (ServeCommand::$passthroughVariables), waardoor elk webverzoek
# daar ineens op de testdatabase uitkwam. Daarom wordt dit bestand hier afgeleid
# uit de .env van DEZE machine: de inloggegevens kloppen dan altijd, en er staat
# nergens een wachtwoord in Git.
#
# Dit is afgeleide staat: het wordt bij elke start opnieuw geschreven. Handmatige
# wijzigingen gaan verloren; pas .env aan, niet .env.testing.
# ============================================================================
$ErrorActionPreference = 'Stop'
$proj = Split-Path $PSScriptRoot -Parent
$envPad = Join-Path $proj '.env'
$doelPad = Join-Path $proj '.env.testing'

if (-not (Test-Path $envPad)) {
    Write-Host "Geen .env gevonden; .env.testing wordt overgeslagen." -ForegroundColor Yellow
    return
}

# .env uitlezen als sleutel/waarde.
$waarden = @{}
foreach ($regel in Get-Content $envPad) {
    if ($regel -match '^\s*([A-Z0-9_]+)\s*=\s*(.*)$') {
        $waarden[$Matches[1]] = $Matches[2].Trim('"').Trim()
    }
}

# APP_ENV=testing in .env is op een ontwikkelmachine bijna altijd fout:
# `php artisan serve` geeft APP_ENV door aan het webserverproces, waardoor ELK
# webverzoek .env.testing laadt en dus op de TESTdatabase uitkomt. Symptoom:
# "Access denied for user ... Database: <naam>_test" of een lege applicatie.
if ($waarden['APP_ENV'] -eq 'testing') {
    Write-Host ''
    Write-Host '  LET OP: in .env staat APP_ENV=testing.' -ForegroundColor Red
    Write-Host '  Daardoor komen uw WEBVERZOEKEN op de testdatabase uit in plaats' -ForegroundColor Red
    Write-Host '  van op de ontwikkeldatabase. Zet APP_ENV=local in .env.' -ForegroundColor Red
    Write-Host ''
}

$db = $waarden['DB_DATABASE']
if ([string]::IsNullOrWhiteSpace($db)) { $db = 'iuasr_sis' }
# De testdatabase is altijd de ontwikkeldatabase + _test; nooit dezelfde.
$testDb = if ($db.EndsWith('_test')) { $db } else { $db + '_test' }

# Eerst uitrekenen, dan invullen: PowerShell 5.1 struikelt over
# $($hashtable['sleutel']) binnen een here-string.
$appKey = $waarden['APP_KEY']
$dbConnection = $waarden['DB_CONNECTION']
$dbHost = $waarden['DB_HOST']
$dbPort = $waarden['DB_PORT']
$dbUser = $waarden['DB_USERNAME']
$dbPass = $waarden['DB_PASSWORD']

$inhoud = @"
# ============================================================================
# GEGENEREERD door scripts/env-testing.ps1. Niet handmatig aanpassen en niet in
# Git zetten: dit bevat de inloggegevens van deze machine. Pas .env aan; dit
# bestand wordt bij elke start van dev.ps1 opnieuw afgeleid.
#
# Doel: `php artisan <commando> --env=testing` komt hierdoor GEGARANDEERD op
# $testDb uit. Zonder dit bestand valt Laravel terug op .env en dus op de
# ontwikkeldatabase; zo is op 17-07-2026 de hele ontwikkeldatabase gewist.
# ============================================================================
APP_ENV=testing
APP_DEBUG=true
APP_KEY=$appKey

DB_CONNECTION=$dbConnection
DB_HOST=$dbHost
DB_PORT=$dbPort
DB_DATABASE=$testDb
DB_USERNAME=$dbUser
DB_PASSWORD=$dbPass

CACHE_STORE=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
MAIL_MAILER=log

# Geen netwerkbeperking in tests.
SIS_TOEGESTANE_IPS=
"@

Set-Content -Path $doelPad -Value $inhoud -Encoding utf8
Write-Host ".env.testing afgeleid van .env (database: $testDb)" -ForegroundColor DarkGray
