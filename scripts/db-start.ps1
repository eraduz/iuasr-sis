# ============================================================================
# db-start.ps1 — start de lokale, portable MariaDB (ontwikkeling)
# Draait op poort 3307 vanuit het gebruikersprofiel; geen Windows-service,
# geen adminrechten, geen conflict met een eventuele MySQL op 3306.
# ============================================================================
$ErrorActionPreference = 'Stop'

$home_   = "$env:USERPROFILE\mariadb\mariadb-11.4.9-winx64"
$cfg     = "$env:USERPROFILE\mariadb\my.ini"
$pidFile = "$env:USERPROFILE\mariadb\mariadbd.pid"

if (-not (Test-Path $home_)) { Write-Error "MariaDB niet gevonden in $home_. Zie docs/ONTWIKKELOMGEVING.md."; exit 1 }

$running = Get-CimInstance Win32_Process -Filter "Name='mariadbd.exe'" -ErrorAction SilentlyContinue
if ($running) {
    Write-Host "MariaDB draait al (PID $($running.ProcessId)) op poort 3307." -ForegroundColor Green
    return
}

$p = Start-Process -FilePath "$home_\bin\mariadbd.exe" -ArgumentList "--defaults-file=`"$cfg`"" -WindowStyle Hidden -PassThru
$p.Id | Set-Content $pidFile
Write-Host "MariaDB gestart (PID $($p.Id))." -ForegroundColor Green

foreach ($i in 1..30) {
    Start-Sleep -Milliseconds 600
    if ((Test-NetConnection 127.0.0.1 -Port 3307 -WarningAction SilentlyContinue).TcpTestSucceeded) {
        Write-Host "Klaar: poort 3307 bereikbaar." -ForegroundColor Green
        return
    }
}
Write-Warning "MariaDB lijkt nog niet te luisteren op 3307. Controleer $home_\..\..\mariadb-data\*.err"
