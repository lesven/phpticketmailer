# Deployment-Script für das Ticketumfrage-Tool (Windows PowerShell)
# Führt alle notwendigen Schritte aus, um die Anwendung zu aktualisieren und neu zu deployen

# Fehlererkennung aktivieren
$ErrorActionPreference = "Stop"

# Hilfsfunktionen für farbige Ausgaben
function Write-Info {
    param([string]$Message)
    Write-Host "[INFO] $Message" -ForegroundColor Blue
}

function Write-Success {
    param([string]$Message)
    Write-Host "[SUCCESS] $Message" -ForegroundColor Green
}

function Write-Warning {
    param([string]$Message)
    Write-Host "[WARNING] $Message" -ForegroundColor Yellow
}

function Write-Error {
    param([string]$Message)
    Write-Host "[ERROR] $Message" -ForegroundColor Red
}

try {
    # Sicherstellen, dass wir im richtigen Verzeichnis sind
    if (-not (Test-Path "docker-compose.yml")) {
        Write-Error "Das Script muss im Hauptverzeichnis der Anwendung ausgeführt werden!"
        exit 1
    }

    # Aktuelle Änderungen vom Git-Repository abholen
    Write-Info "Aktualisiere Code aus dem Git-Repository..."
    git pull

    # Docker-Container stoppen und neu bauen
    Write-Info "Stoppe laufende Docker-Container..."
    docker compose down

    Write-Info "Baue Docker-Container neu..."
    docker compose build

    # Container starten
    Write-Info "Starte Docker-Container..."
    docker compose up -d

    # Warten, bis die Container vollständig hochgefahren sind
    Write-Info "Warte 10 Sekunden, bis die Container vollständig gestartet sind..."
    Start-Sleep -Seconds 10

    # Windows-Zeilenendings in kritischen Dateien korrigieren
    Write-Info "Korrigiere Zeilenendings in Container-Dateien..."
    docker compose exec -T php sh -c "find . -name '*.php' -type f -exec dos2unix {} \; 2>/dev/null || find . -name '*.php' -type f -exec sed -i 's/\r$//' {} \;"
    docker compose exec -T php sh -c "find bin -type f -exec dos2unix {} \; 2>/dev/null || find bin -type f -exec sed -i 's/\r$//' {} \;"
    docker compose exec -T php sh -c "dos2unix bin/console 2>/dev/null || sed -i 's/\r$//' bin/console"

    # Berechtigungen für ausführbare Dateien setzen
    Write-Info "Setze Ausführungsberechtigungen..."
    docker compose exec -T php chmod +x bin/console

    # Composer-Abhängigkeiten aktualisieren
    Write-Info "Aktualisiere Composer-Abhängigkeiten..."
    docker compose exec -T php composer install

    # Cache löschen
    Write-Info "Lösche Symfony-Cache..."
    docker compose exec -T php bin/console cache:clear

    # Datenbank-Migrations ausführen
    Write-Info "Führe Datenbank-Migrationen aus..."
    docker compose exec -T php bin/console doctrine:migrations:migrate --no-interaction

    # Datenbankschema aktualisieren (falls nötig)
    Write-Info "Aktualisiere Datenbankschema..."
    docker compose exec -T php bin/console doctrine:schema:update --force --complete

    # Versionsinformationen aktualisieren
    if (Test-Path "VERSION") {
        Write-Info "Aktualisiere Versionsinformationen..."
        
        # Aktuelle Version aus der Datei lesen und Patch-Version erhöhen
        $currentVersionLine = Get-Content "VERSION" | Select-Object -First 1
        $currentVersion = $currentVersionLine.Split('|')[0]
        
        if ($currentVersion -match "^(\d+)\.(\d+)\.(\d+)$") {
            $major = [int]$matches[1]
            $minor = [int]$matches[2]
            $patch = [int]$matches[3]
            # Patch-Version erhöhen
            $patch++
            $newVersion = "$major.$minor.$patch"
        } else {
            # Wenn keine gültige Version gefunden wurde, Standardwert setzen
            $newVersion = "1.0.0"
        }
        
        # Neue Version und Update-Zeitstempel setzen
        Write-Info "Setze neue Version auf $newVersion..."
        docker compose exec -T php bin/console app:update-version --version="$newVersion"
        
        Write-Success "Versionsinformationen wurden aktualisiert auf $newVersion."
    }

    # Berechtigungen für Cache- und Log-Verzeichnisse setzen
    Write-Info "Setze Berechtigungen für Cache- und Log-Verzeichnisse..."
    docker compose exec -T php chmod -R 777 var/cache var/log

    # Status der Docker-Container anzeigen
    Write-Info "Status der Docker-Container:"
    docker compose ps

    Write-Success "Deployment abgeschlossen! Die Anwendung sollte jetzt aktualisiert und bereit sein."
    Write-Info "Falls Probleme auftreten, überprüfen Sie die Logs mit: docker compose logs -f"
    
    if (Test-Path "VERSION") {
        $versionInfo = Get-Content "VERSION" -Raw
        Write-Info "Aktuelle Version: $versionInfo"
    }

} catch {
    Write-Error "Fehler während des Deployments: $($_.Exception.Message)"
    exit 1
}
