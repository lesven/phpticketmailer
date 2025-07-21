# E-Mail-Statistiken Implementation - Issue #2

## Übersicht

Diese Implementierung fügt E-Mail-Statistiken zum Dashboard des Ticketumfrage-Tools hinzu. Die Statistiken zeigen wichtige Kennzahlen über die versendeten E-Mails an.

## Implementierte Features

### 1. Repository-Erweiterungen (EmailSentRepository.php)

Neue Methoden für Statistiken:
- `countSuccessfulEmails()`: Zählt erfolgreich versendete E-Mails
- `countUniqueRecipients()`: Zählt einzigartige Benutzer, die E-Mails erhalten haben
- `countTotalEmails()`: Zählt alle E-Mail-Versandversuche
- `countFailedEmails()`: Zählt fehlgeschlagene E-Mail-Versendungen
- `getEmailStatistics()`: Sammelt alle Statistiken in einer Abfrage

### 2. Controller-Erweiterungen (DashboardController.php)

- Dashboard-Route erweitert um Statistiken
- Neue API-Route `/api/statistics` für JSON-basierte Statistiken
- Saubere Trennung zwischen HTML- und API-Antworten

### 3. Template-Erweiterungen (dashboard/index.html.twig)

Neue Statistik-Karten zeigen:
- **Gesamt E-Mails**: Alle Versandversuche
- **Erfolgreich**: Zugestellte E-Mails
- **Fehlgeschlagen**: E-Mails mit Fehlern
- **Übersprungen**: Nicht versendete E-Mails (Duplikate, ausgeschlossene Benutzer)
- **Einzigartige Benutzer**: Anzahl unterschiedlicher Empfänger
- **Erfolgsrate**: Prozentsatz erfolgreicher Zustellungen

### 4. Visuelle Gestaltung

- Farbkodierte Karten für bessere Übersicht:
  - Blau: Gesamt E-Mails
  - Grün: Erfolgreich
  - Rot: Fehlgeschlagen
  - Gelb: Übersprungen
  - Cyan: Einzigartige Benutzer
  - Grau: Erfolgsrate

## API-Endpunkt

### GET `/api/statistics`

Gibt JSON-Statistiken zurück:

```json
{
  "total": 150,
  "successful": 142,
  "failed": 3,
  "skipped": 5,
  "unique_recipients": 75,
  "success_rate": 94.7
}
```

## Technische Details

### Datenbankabfragen

Die Implementierung nutzt optimierte Doctrine-Abfragen:
- `COUNT()` für Zählungen
- `COUNT(DISTINCT)` für einzigartige Benutzer
- Filterung nach Status für spezifische Kategorien

### Performance

- Alle Statistiken werden in einer einzigen Methode gesammelt
- Keine N+1-Abfrage-Probleme
- Effiziente SQL-Abfragen durch Query Builder

## Tests

Funktionale Tests für:
- Dashboard-Statistik-Anzeige
- API-Endpunkt-Funktionalität
- JSON-Struktur-Validierung

## Verwendung

Nach der Implementierung werden die Statistiken automatisch auf dem Dashboard angezeigt. Die Daten werden bei jedem Seitenaufruf aktualisiert.

### Für Entwickler

Die API-Route kann für:
- AJAX-basierte Updates
- Externe Monitoring-Systeme
- Dashboard-Widgets
- Reporting-Tools

verwendet werden.

## Zukünftige Erweiterungen

Mögliche weitere Features:
- Zeitbasierte Statistiken (täglich/wöchentlich/monatlich)
- Statistiken nach Testmodus vs. Live-Modus
- Export-Funktionalität für Statistiken
- Grafische Darstellung (Charts)
- Real-time Updates via WebSocket

## Kompatibilität

Diese Implementierung ist vollständig rückwärtskompatibel und ändert keine bestehenden Funktionalitäten. Alle neuen Features sind additiv.
