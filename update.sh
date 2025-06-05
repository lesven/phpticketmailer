#!/bin/bash
# Update-Skript für das Ticketumfrage-Tool
# Führt Updates durch und aktualisiert die Versionsangabe

# Aktuelles Verzeichnis des Skripts bestimmen
SCRIPT_DIR=$(dirname "$(readlink -f "$0")")
cd "$SCRIPT_DIR" || exit 1

# Prüfen, ob eine neue Version übergeben wurde
if [ -n "$1" ]; then
    VERSION="$1"
else
    # Aktuelle Version aus der Datei lesen und Patch-Version erhöhen
    CURRENT_VERSION=$(head -n1 VERSION | cut -d'|' -f1)
    if [[ $CURRENT_VERSION =~ ^([0-9]+)\.([0-9]+)\.([0-9]+)$ ]]; then
        MAJOR="${BASH_REMATCH[1]}"
        MINOR="${BASH_REMATCH[2]}"
        PATCH="${BASH_REMATCH[3]}"
        # Patch-Version erhöhen
        PATCH=$((PATCH + 1))
        VERSION="$MAJOR.$MINOR.$PATCH"
    else
        # Wenn keine gültige Version gefunden wurde, Standardwert setzen
        VERSION="1.0.0"
    fi
fi

echo "Aktuelle Version: $VERSION"

# Git-Updates durchführen, falls Git verwendet wird
if [ -d ".git" ]; then
    echo "Git-Repository gefunden, führe Updates durch..."
    git pull
fi

# Composer-Abhängigkeiten aktualisieren
echo "Aktualisiere Composer-Abhängigkeiten..."
composer install --no-interaction --optimize-autoloader

# Datenbank-Migrationen durchführen
echo "Führe Datenbank-Migrationen durch..."
php bin/console doctrine:migrations:migrate --no-interaction

# Cache leeren
echo "Leere Cache..."
php bin/console cache:clear --no-warmup
php bin/console cache:warmup

# Assets installieren
echo "Installiere Assets..."
php bin/console assets:install public

# Version aktualisieren
echo "Aktualisiere Versionsnummer auf $VERSION..."
php bin/console app:update-version --version="$VERSION"

echo "Update abgeschlossen!"
