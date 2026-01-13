# Statistik-Cache Implementierung

## Übersicht

Die Statistiken werden nun gecached, um die Performance zu verbessern und die Datenbank zu entlasten.

## Implementierte Features

### 1. Cache in StatisticsService

- **Cache-Keys:**
  - `statistics.monthly_user_by_domain_{months}` - für Benutzerstatistiken
  - `statistics.monthly_ticket_by_domain_{months}` - für Ticketstatistiken

- **Cache-TTL:** 1 Stunde (3600 Sekunden)

- **Methoden:**
  - `getMonthlyUserStatisticsByDomain()` - Nutzt Cache für Benutzerstatistiken
  - `getMonthlyTicketStatisticsByDomain()` - Nutzt Cache für Ticketstatistiken
  - `clearCache()` - Löscht alle Cache-Einträge für Statistiken (1-12 Monate)

### 2. Automatisches Cache-Löschen beim CSV-Upload

Der Cache wird automatisch gelöscht, wenn eine neue CSV-Datei verarbeitet wird:
- In `CsvUploadOrchestrator::processUpload()` wird `statisticsService->clearCache()` aufgerufen
- Dies stellt sicher, dass die Statistiken nach dem Versand neuer E-Mails aktuell sind

### 3. Manuelles Cache-Löschen über Dashboard

- **Route:** `/cache/clear` (Name: `cache_clear`)
- **Controller:** `DashboardController::clearCache()`
- **UI:** Button "Cache löschen" im Statistik-Block auf der Dashboard-Seite
- **Feedback:** Erfolgs-Flash-Nachricht nach dem Löschen

## Technische Details

### Verwendete Symfony-Komponenten
- `Symfony\Contracts\Cache\CacheInterface` - Cache-Interface
- `Symfony\Contracts\Cache\ItemInterface` - Cache-Item für TTL-Konfiguration

### Cache-Adapter
Der Cache verwendet den Standard-Cache-Pool von Symfony, der in `config/packages/cache.yaml` konfiguriert ist.

## Tests

Alle Tests wurden aktualisiert, um die Cache-Funktionalität zu berücksichtigen:

- `StatisticsServiceTest` - Mock für CacheInterface hinzugefügt
- `CsvUploadOrchestratorTest` - Erwartung für clearCache()-Aufruf hinzugefügt
- Neuer Test `testClearCacheDeletesAllCacheKeys()` für die clearCache()-Methode

## Verwendung

### Automatisch
Der Cache wird automatisch verwendet und bei CSV-Upload gelöscht. Keine Aktion erforderlich.

### Manuell
1. Zur Dashboard-Seite navigieren
2. Im Statistik-Block auf "Cache löschen" klicken
3. Erfolgsmeldung erscheint
4. Statistiken werden beim nächsten Laden neu berechnet
