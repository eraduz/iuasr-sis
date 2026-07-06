# ============================================================================
# dev.ps1 — start de volledige lokale ontwikkelomgeving:
#   1) zet PHP/Composer op het pad (voor deze sessie),
#   2) haalt het ReadOnly-bit weg dat OneDrive op mappen zet (PHP-quirk),
#   3) start MariaDB (poort 3307),
#   4) start de Laravel-ontwikkelserver op http://127.0.0.1:8000
#
# Gebruik vanuit de projectmap:  .\scripts\dev.ps1
# ============================================================================
$ErrorActionPreference = 'Stop'
$proj = Split-Path $PSScriptRoot -Parent

# 1) PHP + Composer op het pad
$env:Path = "$env:USERPROFILE\php\8.3;$env:USERPROFILE\bin;$env:Path"

# 2) OneDrive zet het ReadOnly-attribuut op mappen; PHP's is_writable() ziet dat
#    ten onrechte als 'niet schrijfbaar'. Weghalen op de mappen die Laravel
#    beschrijft, zodat cache/sessions/views/logs werken.
& attrib -R "$proj\bootstrap\cache" /D 2>&1 | Out-Null
& attrib -R "$proj\storage" /S /D 2>&1 | Out-Null

# 3) Database starten
& "$PSScriptRoot\db-start.ps1"

# 4) Ontwikkelserver starten (op de voorgrond; Ctrl+C om te stoppen)
Set-Location $proj
Write-Host "Start Laravel op http://127.0.0.1:8000  (Ctrl+C om te stoppen)" -ForegroundColor Cyan
& php artisan serve --host=127.0.0.1 --port=8000
