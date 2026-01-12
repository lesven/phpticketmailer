# Ticket-Statistiken nach Domain - Implementation

## Übersicht

Diese Implementierung fügt eine neue Statistiksektion zum Dashboard hinzu, die Tickets pro Domain für die letzten 6 Monate anzeigt. Die Darstellung ist analog zur bestehenden Benutzerstatistik nach Domain.

## Implementierte Features

### 1. Repository-Erweiterung (EmailSentRepository.php)

Neue Methode für Ticket-Statistiken:
- `getMonthlyTicketStatisticsByDomain()`: Zählt einzigartige Tickets pro Domain für die letzten 6 Monate

#### Funktionsweise

Die Methode:
- Berechnet die letzten 6 Monate (aktueller Monat + 5 vorherige Monate)
- Nutzt optimierte MySQL/MariaDB SQL-Abfragen mit `COUNT(DISTINCT ticket_id)`
- Fällt zurück auf PHP-basierte Aggregation bei anderen RDBMS
- Zählt nur erfolgreich versendete E-Mails (Status: 'sent', 'Versendet')
- Normalisiert Domains (lowercase, trimmed)
- Sortiert Domains pro Monat nach Ticketanzahl (absteigend)

### 2. Controller-Erweiterung (DashboardController.php)

- Dashboard-Route `index()` erweitert um Ticket-Statistiken
- Neue Variable `monthlyTicketStatistics` an Template übergeben
- Konsistente Struktur mit bestehenden User-Statistiken

### 3. Template-Erweiterung (dashboard/index.html.twig)

Neue Statistik-Sektion zeigt:
- **Ticketstatistik nach Domain (letzte 6 Monate)**
- Tabelle mit drei Spalten:
  - Monat (Format: YYYY-MM)
  - Domain (als farbige Badges mit Ticketanzahl)
  - Anzahl Tickets (Gesamtsumme pro Monat)

### 4. Visuelle Gestaltung

- Identisches Design wie Benutzerstatistik
- Farbkodierte Badges (bg-info) für Domain-Information
- Responsive Tabellen-Darstellung
- "Keine Daten" Hinweis für Monate ohne Tickets

## Datenstruktur

Die Methode `getMonthlyTicketStatisticsByDomain()` gibt ein Array zurück:

```php
[
    [
        'month' => '2026-01',
        'domains' => [
            'company-a.com' => 15,
            'company-b.com' => 8
        ],
        'total_tickets' => 23
    ],
    [
        'month' => '2025-12',
        'domains' => [
            'company-a.com' => 20
        ],
        'total_tickets' => 20
    ],
    // ... weitere 4 Monate
]
```

## Technische Details

### Datenbankabfragen

**MySQL/MariaDB (primär):**
```sql
SELECT DATE_FORMAT(e.timestamp, '%Y-%m') AS month,
       LOWER(TRIM(SUBSTRING_INDEX(e.email, '@', -1))) AS domain,
       COUNT(DISTINCT e.ticket_id) AS tickets
FROM emails_sent e
WHERE e.timestamp >= :fiveMonthsAgo
  AND (e.status = :status OR e.status = :status_plain OR e.status LIKE :status_like)
  AND e.email LIKE '%@%'
GROUP BY month, domain
ORDER BY month ASC, tickets DESC, domain ASC
```

**Fallback (PHP-basiert):**
- Lädt Rohdaten (timestamp, ticket_id, email)
- Aggregiert in PHP nach Monat und Domain
- Zählt einzigartige Ticket-IDs

### Performance

- Optimierte SQL-Abfragen mit GROUP BY
- Effiziente Nutzung von Doctrine Query Builder
- Minimale Datentransfergröße
- O(1) Lookup für Monatsdaten

## Tests

Umfassende Test-Abdeckung für:
1. **testGetMonthlyTicketStatisticsByDomainReturnsLast6Months**: Verifiziert korrekte 6-Monats-Ausgabe
2. **testGetMonthlyTicketStatisticsByDomainIncludesMonthsWithNoData**: Prüft Monate ohne Daten
3. **testGetMonthlyTicketStatisticsByDomainOnlyCountsSuccessfulEmails**: Filtert fehlgeschlagene E-Mails
4. **testGetMonthlyTicketStatisticsByDomainCountsUniqueTicketsPerDomain**: Zählt einzigartige Tickets

## Verwendung

Die Ticket-Statistiken werden automatisch auf dem Dashboard angezeigt. Die Daten werden bei jedem Seitenaufruf neu berechnet.

### Für Entwickler

Die Methode kann auch separat verwendet werden:

```php
$statistics = $emailSentRepository->getMonthlyTicketStatisticsByDomain();

foreach ($statistics as $stat) {
    echo $stat['month'] . ': ' . $stat['total_tickets'] . ' Tickets' . PHP_EOL;
    foreach ($stat['domains'] as $domain => $count) {
        echo '  - ' . $domain . ': ' . $count . PHP_EOL;
    }
}
```

## Business Value

Diese Statistik ermöglicht:
- Identifikation welche Tochterfirma (basierend auf E-Mail-Domain) wie viele Tickets eröffnet hat
- Trendanalyse über 6 Monate
- Vergleich von Ticket-Volumina zwischen verschiedenen Domains
- Ergänzung zur Benutzerstatistik für vollständiges Bild

## Kompatibilität

Diese Implementierung ist vollständig rückwärtskompatibel und ändert keine bestehenden Funktionalitäten. Alle neuen Features sind additiv.

## Zukünftige Erweiterungen

Mögliche weitere Features:
- Export-Funktionalität für Statistiken (CSV/Excel)
- Grafische Darstellung (Charts/Diagramme)
- Filter nach Zeitraum (3/6/12 Monate)
- Drill-down zu einzelnen Tickets pro Domain
- Vergleich Tickets vs. Benutzer pro Domain
