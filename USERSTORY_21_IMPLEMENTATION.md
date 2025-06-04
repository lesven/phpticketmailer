# User Story 21: Zabbix Monitoring Implementation

Dieses Dokument beschreibt die Implementierung von User Story 21: Integration des Ticketumfrage-Tools in das Zabbix-Monitoring des Konzerns.

## Übersicht

Die Implementierung umfasst die Entwicklung von API-Endpunkten für das Monitoring, die Erstellung einer Weboberfläche für die manuelle Überwachung sowie die Bereitstellung von Dokumentation und Tools für die Zabbix-Integration.

## Komponenten

1. **MonitoringController**: Ein REST-Controller zur Bereitstellung von Monitoring-Endpunkten
2. **MonitoringService**: Ein Service zur Prüfung der verschiedenen Systemkomponenten
3. **Web-Interface**: Eine Benutzeroberfläche zur manuellen Überwachung des Systems
4. **Skripte**: Shell- und PowerShell-Skripte für automatisierte Prüfungen
5. **Zabbix-Template**: Vorgefertigtes XML-Template zur Einbindung in Zabbix
6. **Dokumentation**: Detaillierte Anleitung zur Zabbix-Integration

## Implementierte Monitoring-Funktionen

### 1. Datenbankprüfung

- Prüfung der Datenbankverbindung
- Prüfung des Lesezugriffs auf kritische Tabellen (`users`, `emails_sent`, `csv_field_config`)
- Zählung der Datensätze in jeder Tabelle

### 2. Webserverprüfung

- Prüfung der externen Erreichbarkeit des Webservers
- Messung der Antwortzeit
- Verifizierung des HTTP-Statuscodes

### 3. Docker-Container-Prüfung

- Prüfung der Container-Laufzeiten
- Prüfung des Health-Status der Container
- Überwachung aller relevanten Container:
  - `ticketumfrage_php`
  - `ticketumfrage_webserver`
  - `ticketumfrage_database`
  - `ticketumfrage_mailhog`
  - `ticketumfrage_mailserver`

## Nutzung der Monitoring-API

### Endpunkte

- **/monitoring/health**: Überprüft den Gesamtstatus des Systems
- **/monitoring/database**: Überprüft nur den Datenbankstatus
- **/monitoring/webserver**: Überprüft nur den Webserverstatus
- **/monitoring/containers**: Überprüft nur den Docker-Container-Status

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
    },
    "webserver": {
      "status": "ok",
      "url": "http://localhost:8090",
      "error": null,
      "responseTime": 45,
      "statusCode": 200
    },
    "containers": {
      "status": "ok",
      "containers": {
        "ticketumfrage_php": {
          "status": "ok",
          "running": true,
          "health": "healthy",
          "fullStatus": "Up 2 days"
        },
        "ticketumfrage_webserver": {
          "status": "ok",
          "running": true,
          "health": "healthy",
          "fullStatus": "Up 2 days"
        },
        "ticketumfrage_database": {
          "status": "ok",
          "running": true,
          "health": "healthy",
          "fullStatus": "Up 2 days"
        },
        "ticketumfrage_mailhog": {
          "status": "ok",
          "running": true,
          "health": "N/A",
          "fullStatus": "Up 2 days"
        },
        "ticketumfrage_mailserver": {
          "status": "ok",
          "running": true,
          "health": "N/A",
          "fullStatus": "Up 2 days"
        }
      },
      "error": null
    }
  }
}
```

## Web-Interface

Ein interaktives Web-Interface zur Überwachung des Systems ist unter `/monitoring` verfügbar. Dieses Interface stellt die gleichen Informationen wie die API-Endpunkte dar, bietet jedoch eine benutzerfreundliche Visualisierung des Systemstatus und zeigt detaillierte Informationen zu jeder Komponente.

## Zabbix-Integration

Für die Integration mit Zabbix wurden folgende Dateien bereitgestellt:

1. **Zabbix-Template**: `zabbix/template_ticketumfrage.xml`
2. **Dokumentation**: `ZABBIX_MONITORING.md`
3. **Monitoring-Scripts**:
   - Bash-Script: `scripts/check_ticketmailer.sh`
   - PowerShell-Script: `scripts/Check-TicketmailerHealth.ps1`

## Test-Abdeckung

Die Implementierung enthält Unit-Tests für den MonitoringService, die die korrekte Funktion der Monitoring-Komponenten sicherstellen.

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
