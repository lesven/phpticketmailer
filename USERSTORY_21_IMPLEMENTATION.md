# User Story 21: Zabbix Monitoring Implementation

Dieses Dokument beschreibt die Implementierung von User Story 21: Integration des Ticketumfrage-Tools in das Zabbix-Monitoring des Konzerns.

## Übersicht

Die Implementierung umfasst die Entwicklung von API-Endpunkten für das Datenbank-Monitoring, die Erstellung einer Weboberfläche für die manuelle Überwachung sowie die Bereitstellung von Dokumentation und Tools für die Zabbix-Integration.

## Komponenten

1. **MonitoringController**: Ein REST-Controller zur Bereitstellung von Monitoring-Endpunkten
2. **MonitoringService**: Ein Service zur Prüfung der Datenbankverbindung und -zugriffs
3. **Web-Interface**: Eine Benutzeroberfläche zur manuellen Überwachung des Systems
4. **Skripte**: Shell- und PowerShell-Skripte für automatisierte Prüfungen
5. **Zabbix-Template**: Vorgefertigtes XML-Template zur Einbindung in Zabbix
6. **Dokumentation**: Detaillierte Anleitung zur Zabbix-Integration

## Implementierte Monitoring-Funktionen

### Datenbankprüfung

- Prüfung der Datenbankverbindung
- Prüfung des Lesezugriffs auf kritische Tabellen (`users`, `emails_sent`, `csv_field_config`)
- Zählung der Datensätze in jeder Tabelle

## Nutzung der Monitoring-API

### Endpunkte

- **/monitoring/health**: Überprüft den Gesamtstatus des Systems (Datenbankverbindung)
- **/monitoring/database**: Überprüft detailliert den Datenbankstatus

### Beispiel-Anfrage

```bash
curl http://localhost:8090/monitoring/health
```

### Beispiel-Antwort

```json
{
  "status": "ok",
  "timestamp": "2025-06-04T12:34:56+02:00",
  "checks": {
    "database": {
      "status": "ok",
      "tables": {
        "users": {
          "status": "ok",
          "recordCount": 142
        },
        "emails_sent": {
          "status": "ok",
          "recordCount": 1250
        },
        "csv_field_config": {
          "status": "ok",
          "recordCount": 3
        }
      },
      "error": null
    }
  }
}
```

## Web-Interface

Ein interaktives Web-Interface zur Überwachung des Datenbankstatus ist unter `/monitoring` verfügbar. Dieses Interface stellt die gleichen Informationen wie die API-Endpunkte dar, bietet jedoch eine benutzerfreundliche Visualisierung des Datenbankstatus und zeigt detaillierte Informationen zu den überwachten Tabellen.

## Zabbix-Integration

Für die Integration mit Zabbix wurden folgende Dateien bereitgestellt:

1. **Zabbix-Template**: `zabbix/template_ticketumfrage.xml`
2. **Dokumentation**: `ZABBIX_MONITORING.md`
3. **Monitoring-Scripts**:
   - Bash-Script: `scripts/check_ticketmailer.sh`
   - PowerShell-Script: `scripts/Check-TicketmailerHealth.ps1`

## Test-Abdeckung

Die Implementierung enthält Unit-Tests für den MonitoringService, die die korrekte Funktion der Datenbank-Monitoring-Komponenten sicherstellen.

## Dateien

- **Controller**: `src/Controller/MonitoringController.php`
- **Service**: `src/Service/MonitoringService.php`
- **Template**: `templates/monitoring/index.html.twig`
- **CSS**: `public/css/monitoring.css`
- **Tests**: `tests/Service/MonitoringServiceTest.php`
- **Skripte**:
  - `scripts/check_ticketmailer.sh`
  - `scripts/Check-TicketmailerHealth.ps1`
- **Dokumentation**:
  - `ZABBIX_MONITORING.md`
  - `USERSTORY_21_IMPLEMENTATION.md` (diese Datei)
- **Zabbix-Konfiguration**:
  - `zabbix/template_ticketumfrage.xml`
