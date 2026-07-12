# Veilige update tijdens het testen (Windows PowerShell).
# Haalt de laatste code op en draait daarna ALLEEN de nieuwe migraties, met een
# veiligheidssnapshot vooraf. Wist NOOIT de testdata. Zie TESTEN.md.
#
# Gebruik:  rechtsklik > "Uitvoeren met PowerShell", of:  ./update.ps1

$ErrorActionPreference = 'Stop'
Set-Location -Path $PSScriptRoot

Write-Host '== Code ophalen (git pull) ==' -ForegroundColor Cyan
git pull

Write-Host ''
Write-Host '== Database veilig bijwerken (snapshot + migrate) ==' -ForegroundColor Cyan
php artisan sis:update

Write-Host ''
Write-Host 'Klaar. Je testdata is behouden.' -ForegroundColor Green
