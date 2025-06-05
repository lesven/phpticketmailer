# Update-Skript für das Ticketumfrage-Tool (PowerShell-Version)
# Führt Updates durch und aktualisiert die Versionsangabe

param (
    [string]$Version = ""
)

# Aktuelles Verzeichnis des Skripts bestimmen
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location -Path $ScriptDir

# Prüfen, ob eine neue Version übergeben wurde
if ([string]::IsNullOrEmpty($Version)) {
    # Aktuelle Version aus der Datei lesen und Patch-Version erhöhen
    $VersionContent = Get-Content -Path "VERSION" -TotalCount 1
    $CurrentVersion = $VersionContent.Split('|')[0].Trim()
    
    if ($CurrentVersion -match "^(\d+)\.(\d+)\.(\d+)$") {
        $Major = [int]$Matches[1]
        $Minor = [int]$Matches[2]
        $Patch = [int]$Matches[3]
        # Patch-Version erhöhen
        $Patch = $Patch + 1
        $Version = "$Major.$Minor.$Patch"
    } else {
        # Wenn keine gültige Version gefunden wurde, Standardwert setzen
        $Version = "1.0.0"
    }
}

Write-Host "Aktuelle Version: $Version"

# Git-Updates durchführen, falls Git verwendet wird
if (Test-Path ".git") {
    Write-Host "Git-Repository gefunden, führe Updates durch..."
    git pull
}

# Composer-Abhängigkeiten aktualisieren
Write-Host "Aktualisiere Composer-Abhängigkeiten..."
composer install --no-interaction --optimize-autoloader

# Datenbank-Migrationen durchführen
Write-Host "Führe Datenbank-Migrationen durch..."
php bin/console doctrine:migrations:migrate --no-interaction

# Cache leeren
Write-Host "Leere Cache..."
php bin/console cache:clear --no-warmup
php bin/console cache:warmup

# Assets installieren
Write-Host "Installiere Assets..."
php bin/console assets:install public

# Version aktualisieren
Write-Host "Aktualisiere Versionsnummer auf $Version..."
php bin/console app:update-version --version="$Version"

Write-Host "Update abgeschlossen!" -ForegroundColor Green
