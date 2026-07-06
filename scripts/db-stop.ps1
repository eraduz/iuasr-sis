# ============================================================================
# db-stop.ps1 — stop de lokale MariaDB netjes af.
# ============================================================================
$ErrorActionPreference = 'SilentlyContinue'

$home_ = "$env:USERPROFILE\mariadb\mariadb-11.4.9-winx64"
$admin = "$home_\bin\mariadb-admin.exe"

if (Test-Path $admin) {
    & $admin --port=3307 --host=127.0.0.1 -u root shutdown 2>$null
}

Start-Sleep -Seconds 2
$still = Get-CimInstance Win32_Process -Filter "Name='mariadbd.exe'" -ErrorAction SilentlyContinue
if ($still) {
    $still | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }
    Write-Host "MariaDB geforceerd gestopt." -ForegroundColor Yellow
} else {
    Write-Host "MariaDB gestopt." -ForegroundColor Green
}
