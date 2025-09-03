# Versionsverwaltung im Ticketumfrage-Tool

Diese Dokumentation beschreibt das Versionsverwaltungssystem des Ticketumfrage-Tools, das im Rahmen von Userstory 22 implementiert wurde.

## Übersicht

Das Versionsverwaltungssystem besteht aus folgenden Komponenten:

1. Eine `VERSION`-Datei im Wurzelverzeichnis des Projekts
2. Ein `VersionService`, der die Versionsinformationen verwaltet
3. Eine Twig-Erweiterung, die die Version im Template verfügbar macht
4. Ein Symfony-Befehl zum Aktualisieren der Version
5. Update-Skripte für Linux (update.sh) und Windows (Update-Ticketumfrage.ps1)

## VERSION-Datei

Die `VERSION`-Datei enthält zwei durch ein Pipe-Symbol (|) getrennte Werte:

1. Die Versionsnummer im Format `X.Y.Z` (Major.Minor.Patch)
2. Den Zeitstempel der letzten Aktualisierung im Format `YYYY-MM-DD HH:MM:SS`

Beispiel: `1.0.0|2025-06-05 10:30:42`

## Komponenten

### VersionService

Der `VersionService` (in `src/Service/VersionService.php`) stellt Methoden zum Lesen und Aktualisieren der Versionsinformationen bereit.

Wichtige Methoden:
- `getVersion()`: Gibt die aktuelle Versionsnummer zurück
- `getUpdateTimestamp()`: Gibt den Zeitstempel des letzten Updates zurück
- `getFormattedVersionString()`: Gibt eine formatierte Versionszeichenkette zurück
- `updateVersionInfo()`: Aktualisiert die Versionsdatei

### Twig-Erweiterung (VersionExtension)

Die `VersionExtension` (in `src/Twig/VersionExtension.php`) macht die Versionsinformationen in Twig-Templates verfügbar.

Verfügbare Funktionen:
- `app_version()`: Gibt die Versionsnummer zurück
- `app_update_timestamp()`: Gibt den Update-Zeitstempel zurück
- `app_version_string()`: Gibt eine formatierte Versionszeichenkette zurück

### Symfony-Befehl

Der Befehl `app:update-version` (in `src/Command/UpdateVersionCommand.php`) kann verwendet werden, um die Versionsinformationen zu aktualisieren.

Verwendung:
```bash
php bin/console app:update-version --new-version="1.2.3"
```

Optionen:
- `--new-version=X.Y.Z`: Setzt eine neue Versionsnummer
- `--no-timestamp`: Verhindert die Aktualisierung des Zeitstempels

### Update-Skripte

Es gibt zwei Update-Skripte:

1. `update.sh` für Linux/macOS
2. `Update-Ticketumfrage.ps1` für Windows

Diese Skripte führen ein vollständiges Update der Anwendung durch und aktualisieren automatisch die Versionsnummer.

Verwendung:
```bash
# Linux/macOS
./update.sh [neue-version]

# Windows PowerShell
./Update-Ticketumfrage.ps1 [-Version "neue-version"]
```

Wenn keine Version angegeben wird, wird die Patch-Version automatisch erhöht.

## Anzeige im Frontend

Die Version wird im Footer der Anwendung angezeigt. 

Die Anzeige folgt dem Format: `Version X.Y.Z (Stand: YYYY-MM-DD HH:MM:SS)`

## Automatische Aktualisierung

Bei jedem Update durch die Update-Skripte wird die Versionsnummer automatisch aktualisiert. Dies stellt sicher, dass Systemadministratoren immer die aktuell installierte Version sehen können.

## Beispiel für ein manuelles Update

Wenn Sie die Version manuell aktualisieren möchten:

```bash
# Linux/macOS
php bin/console app:update-version --new-version="1.2.3"

# Windows
php bin/console app:update-version --new-version="1.2.3"
```
