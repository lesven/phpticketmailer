#!/bin/bash
# Deployment-Script für das Ticketumfrage-Tool
# Führt alle notwendigen Schritte aus, um die Anwendung zu aktualisieren und neu zu deployen

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

# Sicherstellen, dass wir im richtigen Verzeichnis sind
if [ ! -f "docker-compose.yml" ]; then
    log_error "Das Script muss im Hauptverzeichnis der Anwendung ausgeführt werden!"
    exit 1
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

# Status der Docker-Container anzeigen
log_info "Status der Docker-Container:"
docker compose ps

log_success "Deployment abgeschlossen! Die Anwendung sollte jetzt aktualisiert und bereit sein."
log_info "Falls Probleme auftreten, überprüfen Sie die Logs mit: docker compose logs -f"
if [ -f "VERSION" ]; then
    VERSION_INFO=$(cat VERSION)
    log_info "Aktuelle Version: $VERSION_INFO"
fi