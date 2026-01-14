# Statistik-Cache Implementierung

## Übersicht

Die Statistiken werden nun gecached, um die Performance zu verbessern und die Datenbank zu entlasten.

## Implementierte Features

### 1. Cache in StatisticsService

- **Cache-Keys:**
  - `statistics.monthly_user_by_domain_{months}` - für Benutzerstatistiken
  - `statistics.monthly_ticket_by_domain_{months}` - für Ticketstatistiken

- **Cache-TTL:** 48 Stunden (172800 Sekunden)

- **Methoden:**
  - `getMonthlyUserStatisticsByDomain()` - Nutzt Cache für Benutzerstatistiken
  - `getMonthlyTicketStatisticsByDomain()` - Nutzt Cache für Ticketstatistiken
  - `clearCurrentMonthCache()` - Löscht nur den Cache für den Standard-Zeitraum (6 Monate), wird beim CSV-Upload aufgerufen
  - `clearCache()` - Löscht alle Cache-Einträge für Statistiken (1-12 Monate), wird über UI aufgerufen

### 2. Automatisches Cache-Löschen beim CSV-Upload

Der Cache für den aktuellen Monat wird automatisch gelöscht, wenn eine neue CSV-Datei verarbeitet wird:
- In `CsvUploadOrchestrator::processUpload()` wird `statisticsService->clearCurrentMonthCache()` aufgerufen
- Dies löscht nur den Standard-Cache (6 Monate), um Performance zu optimieren
- Ältere gecachte Statistiken bleiben erhalten

### 3. Manuelles Cache-Löschen über Dashboard

- **Route:** `/cache/clear` (Name: `cache_clear`)
- **Controller:** `DashboardController::clearCache()`
- **UI:** Button "Cache löschen" im Statistik-Block auf der Dashboard-Seite
- **Feedback:** Erfolgs-Flash-Nachricht nach dem Löschen
- **Funktion:** Löscht alle Cache-Einträge (1-12 Monate)

## Technische Details

### Verwendete Symfony-Komponenten
- `Symfony\Contracts\Cache\CacheInterface` - Cache-Interface
- `Symfony\Contracts\Cache\ItemInterface` - Cache-Item für TTL-Konfiguration

### Cache-Adapter
Der Cache verwendet den Standard-Cache-Pool von Symfony, der in `config/packages/cache.yaml` konfiguriert ist.

## Tests

Alle Tests wurden aktualisiert, um die Cache-Funktionalität zu berücksichtigen:

- `StatisticsServiceTest` - Mock für CacheInterface hinzugefügt
- `CsvUploadOrchestratorTest` - Erwartung für clearCurrentMonthCache()-Aufruf hinzugefügt
- Neuer Test `testClearCacheDeletesAllCacheKeys()` für die clearCache()-Methode
- Neuer Test `testClearCurrentMonthCacheDeletesOnlyDefaultCache()` für die clearCurrentMonthCache()-Methode

## Verwendung

### Automatisch
Der Cache wird automatisch verwendet. Bei CSV-Upload wird nur der aktuelle Monats-Cache (6 Monate Standard) gelöscht. Keine Aktion erforderlich.

### Manuell
1. Zur Dashboard-Seite navigieren
2. Im Statistik-Block auf "Cache löschen" klicken
3. Erfolgsmeldung erscheint
4. Alle Statistiken-Caches werden gelöscht und beim nächsten Laden neu berechnet
