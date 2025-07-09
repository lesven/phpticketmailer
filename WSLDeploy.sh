#!/bin/bash
# Deployment-Script für das Ticketumfrage-Tool (Windows WSL)
# Führt alle notwendigen Schritte aus, um die Anwendung zu aktualisieren und neu zu deployen
# Optimiert für Windows Subsystem for Linux

# Fehlererkennung aktivieren
set -e

# Farbige Ausgaben für bessere Lesbarkeit
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Hilfsfunktionen
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# WSL-spezifische Hilfsfunktion zur Pfadkonvertierung
convert_wsl_path() {
    local path="$1"
    # Konvertiert Windows-Pfade zu WSL-Pfaden falls nötig
    if [[ "$path" =~ ^[A-Za-z]: ]]; then
        path=$(wslpath "$path" 2>/dev/null || echo "$path")
    fi
    echo "$path"
}

# Sicherstellen, dass wir im richtigen Verzeichnis sind
if [ ! -f "docker-compose.yml" ]; then
    log_error "Das Script muss im Hauptverzeichnis der Anwendung ausgeführt werden!"
    log_info "Aktuelles Verzeichnis: $(pwd)"
    exit 1
fi

# WSL-spezifische Checks
log_info "Überprüfe WSL-Umgebung..."
if grep -qi microsoft /proc/version 2>/dev/null; then
    log_info "WSL-Umgebung erkannt"
    # Docker Desktop für Windows sollte verfügbar sein
    if ! command -v docker >/dev/null 2>&1; then
        log_error "Docker ist nicht verfügbar. Stellen Sie sicher, dass Docker Desktop läuft und WSL-Integration aktiviert ist."
        exit 1
    fi
else
    log_warning "Keine WSL-Umgebung erkannt - führe als normales Linux-Script aus"
fi

# Git-Konfiguration für WSL anpassen (falls nötig)
if git config --get core.autocrlf >/dev/null 2>&1; then
    log_info "Git CRLF-Behandlung ist bereits konfiguriert"
else
    log_info "Konfiguriere Git für Windows/WSL-Kompatibilität..."
    git config core.autocrlf input
fi

# Aktuelle Änderungen vom Git-Repository abholen
log_info "Aktualisiere Code aus dem Git-Repository..."
git pull

# Docker-Container stoppen und neu bauen
log_info "Stoppe laufende Docker-Container..."
docker compose down

log_info "Baue Docker-Container neu..."
docker compose build

# Container starten
log_info "Starte Docker-Container..."
docker compose up -d

# Warten, bis die Container vollständig hochgefahren sind
log_info "Warte 10 Sekunden, bis die Container vollständig gestartet sind..."
sleep 10

# Container-Status überprüfen
log_info "Überprüfe Container-Status..."
if ! docker compose ps | grep -q "Up"; then
    log_error "Container sind nicht erfolgreich gestartet!"
    docker compose logs --tail=20
    exit 1
fi

# Composer-Abhängigkeiten aktualisieren
log_info "Aktualisiere Composer-Abhängigkeiten..."
docker compose exec php composer install

# Cache löschen
log_info "Lösche Symfony-Cache..."
docker compose exec php bin/console cache:clear

# Datenbank-Migrations ausführen
log_info "Führe Datenbank-Migrationen aus..."
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction

# Datenbankschema aktualisieren (falls nötig)
log_info "Aktualisiere Datenbankschema..."
docker compose exec php bin/console doctrine:schema:update --force --complete

# Versionsinformationen aktualisieren
if [ -f "VERSION" ]; then
    log_info "Aktualisiere Versionsinformationen..."
    
    # Aktuelle Version aus der Datei lesen und Patch-Version erhöhen
    CURRENT_VERSION=$(head -n1 VERSION | cut -d'|' -f1)
    if [[ $CURRENT_VERSION =~ ^([0-9]+)\.([0-9]+)\.([0-9]+)$ ]]; then
        MAJOR="${BASH_REMATCH[1]}"
        MINOR="${BASH_REMATCH[2]}"
        PATCH="${BASH_REMATCH[3]}"
        # Patch-Version erhöhen
        PATCH=$((PATCH + 1))
        NEW_VERSION="$MAJOR.$MINOR.$PATCH"
    else
        # Wenn keine gültige Version gefunden wurde, Standardwert setzen
        NEW_VERSION="1.0.0"
    fi
    
    # Neue Version und Update-Zeitstempel setzen
    log_info "Setze neue Version auf $NEW_VERSION..."
    docker compose exec php bin/console app:update-version --version="$NEW_VERSION"
    
    log_success "Versionsinformationen wurden aktualisiert auf $NEW_VERSION."
fi

# Berechtigungen für Cache- und Log-Verzeichnisse setzen
log_info "Setze Berechtigungen für Cache- und Log-Verzeichnisse..."
docker compose exec php chmod -R 777 var/cache var/log

# WSL-spezifische Berechtigungen (falls auf Windows-Laufwerk)
CURRENT_PATH=$(pwd)
if [[ "$CURRENT_PATH" =~ ^/mnt/[a-z]/ ]]; then
    log_info "Windows-Laufwerk erkannt - überspringe lokale Berechtigungsänderungen"
else
    log_info "Setze lokale Dateiberechtigungen..."
    chmod -R 755 . 2>/dev/null || log_warning "Konnte lokale Berechtigungen nicht setzen"
fi

# Status der Docker-Container anzeigen
log_info "Status der Docker-Container:"
docker compose ps

# Abschließende Gesundheitsprüfung
log_info "Führe Gesundheitsprüfung durch..."
if docker compose exec php bin/console --version >/dev/null 2>&1; then
    log_success "Symfony-Console ist erreichbar"
else
    log_warning "Symfony-Console nicht erreichbar - möglicherweise ist die Anwendung noch nicht vollständig gestartet"
fi

log_success "Deployment abgeschlossen! Die Anwendung sollte jetzt aktualisiert und bereit sein."
log_info "Falls Probleme auftreten, überprüfen Sie die Logs mit: docker compose logs -f"
log_info "Für WSL-spezifische Probleme: Stellen Sie sicher, dass Docker Desktop läuft und WSL-Integration aktiviert ist"

if [ -f "VERSION" ]; then
    VERSION_INFO=$(cat VERSION)
    log_info "Aktuelle Version: $VERSION_INFO"
fi

# WSL-spezifische Hinweise
if grep -qi microsoft /proc/version 2>/dev/null; then
    log_info "WSL-Tipps:"
    log_info "- Bei Netzwerkproblemen: Überprüfen Sie die Windows-Firewall"
    log_info "- Bei Dateiberechtigungsproblemen: Verwenden Sie 'wsl --shutdown' und starten Sie WSL neu"
    log_info "- Anwendung erreichbar unter: http://localhost (falls Port-Forwarding konfiguriert ist)"
fi
