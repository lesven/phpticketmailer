# Zabbix Integration für das Ticketumfrage-Tool

Diese Dokumentation beschreibt die Integration des Ticketumfrage-Tools mit dem Zabbix-Monitoring-System.

## Übersicht

Das Ticketumfrage-Tool stellt mehrere JSON-Endpunkte bereit, die zur Überwachung des Systemzustands verwendet werden können. Diese Endpunkte können direkt in Zabbix eingebunden werden, um automatisierte Prüfungen durchzuführen und im Fehlerfall Alarme auszulösen.

## Verfügbare Endpunkte

Alle Monitoring-Endpunkte befinden sich unter dem Pfad `/monitoring/` und geben JSON-Antworten zurück.

1. **Gesamtstatus**: `/monitoring/health`
   - Prüft alle Systemkomponenten und gibt einen konsolidierten Status zurück
   
2. **Datenbankstatus**: `/monitoring/database`
   - Prüft die Erreichbarkeit der Datenbank und den Lesezugriff auf wichtige Tabellen
   
3. **Webserverstatus**: `/monitoring/webserver`
   - Prüft die externe Erreichbarkeit des Webservers
   
4. **Container-Status**: `/monitoring/containers`
   - Prüft den Status aller Docker-Container, die für das System benötigt werden

## Zabbix-Einrichtung

### 1. Hostgruppe und Host erstellen

Erstellen Sie zunächst eine neue Hostgruppe und einen Host in Zabbix:

1. Navigieren Sie zu **Configuration** > **Host groups** > **Create host group**
   - Name: `Ticketumfrage`

2. Navigieren Sie zu **Configuration** > **Hosts** > **Create host**
   - Host name: `ticketumfrage-tool`
   - Groups: `Ticketumfrage`
   - Interfaces: Fügen Sie eine HTTP-Schnittstelle mit der URL Ihres Servers hinzu

### 2. Items für die Überwachung einrichten

Für jeden Endpunkt sollte ein separates Item erstellt werden:

#### Gesamtstatus-Item

1. Navigieren Sie zu **Configuration** > **Hosts** > **Items** > **Create item**
   - Name: `System Health Status`
   - Type: `HTTP agent`
   - Key: `ticketumfrage.health`
   - URL: `http://{HOST.CONN}:{$PORT}/monitoring/health`
   - Request type: `GET`
   - Type of information: `Text`
   - Update interval: `1m` (oder nach Bedarf anpassen)
   - History storage period: `90d`
   - Application: `Ticketumfrage`

#### Datenbank-Status-Item

1. Erstellen Sie ein ähnliches Item:
   - Name: `Database Status`
   - Key: `ticketumfrage.db`
   - URL: `http://{HOST.CONN}:{$PORT}/monitoring/database`
   - (Rest wie oben)

#### Webserver-Status-Item

1. Erstellen Sie ein ähnliches Item:
   - Name: `Webserver Status`
   - Key: `ticketumfrage.web`
   - URL: `http://{HOST.CONN}:{$PORT}/monitoring/webserver`
   - (Rest wie oben)

#### Container-Status-Item

1. Erstellen Sie ein ähnliches Item:
   - Name: `Container Status`
   - Key: `ticketumfrage.containers`
   - URL: `http://{HOST.CONN}:{$PORT}/monitoring/containers`
   - (Rest wie oben)

### 3. Trigger einrichten

Für jedes Item sollten Trigger erstellt werden, die Alarme auslösen, wenn der Status nicht "ok" ist:

#### Health-Trigger

1. Navigieren Sie zu **Configuration** > **Hosts** > **Triggers** > **Create trigger**
   - Name: `Ticketumfrage System Health Problem`
   - Expression: `{ticketumfrage-tool:ticketumfrage.health.regexp(".*\"status\":\"ok\".*")}=0`
   - Priority: `High`
   - Problem event generation mode: `Single`
   - OK event closes: `All problems`
   - Description: `Das Ticketumfrage-System meldet Probleme mit dem Gesamtstatus`

#### Datenbank-Trigger

1. Erstellen Sie einen ähnlichen Trigger:
   - Name: `Ticketumfrage Database Problem`
   - Expression: `{ticketumfrage-tool:ticketumfrage.db.regexp(".*\"status\":\"ok\".*")}=0`
   - (Rest wie oben)
   - Description: `Probleme mit der Datenbank des Ticketumfrage-Tools`

#### Webserver-Trigger

1. Erstellen Sie einen ähnlichen Trigger:
   - Name: `Ticketumfrage Webserver Problem`
   - Expression: `{ticketumfrage-tool:ticketumfrage.web.regexp(".*\"status\":\"ok\".*")}=0`
   - (Rest wie oben)
   - Description: `Der Webserver des Ticketumfrage-Tools ist nicht erreichbar`

#### Container-Trigger

1. Erstellen Sie einen ähnlichen Trigger:
   - Name: `Ticketumfrage Container Problem`
   - Expression: `{ticketumfrage-tool:ticketumfrage.containers.regexp(".*\"status\":\"ok\".*")}=0`
   - (Rest wie oben)
   - Description: `Probleme mit Docker-Containern des Ticketumfrage-Tools`

### 4. Benachrichtigungen einrichten

Richten Sie Aktionen und Benachrichtigungen ein, um bei ausgelösten Triggern automatisch E-Mails oder andere Benachrichtigungen zu senden.

## JSON-Format der Antworten

### Health-Endpunkt

```json
{
  "status": "ok", // oder "error"
  "timestamp": "2025-06-04T15:30:45+02:00",
  "checks": {
    "database": { /* siehe database-Endpunkt */ },
    "webserver": { /* siehe webserver-Endpunkt */ },
    "containers": { /* siehe containers-Endpunkt */ }
  }
}
```

### Database-Endpunkt

```json
{
  "status": "ok", // oder "error"
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
  "error": null // Fehlermeldung falls vorhanden
}
```

### Webserver-Endpunkt

```json
{
  "status": "ok", // oder "error"
  "url": "http://localhost:8090",
  "error": null, // Fehlermeldung falls vorhanden
  "responseTime": 45, // in Millisekunden
  "statusCode": 200
}
```

### Container-Endpunkt

```json
{
  "status": "ok", // oder "error"
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
  "error": null // Fehlermeldung falls vorhanden
}
```

## Fehlerbehebung

### Datenbankfehler

Wenn die Datenbank nicht erreichbar ist:
1. Überprüfen Sie den Status des Datenbankcontainers: `docker ps -a | grep ticketumfrage_database`
2. Falls der Container nicht läuft, starten Sie ihn neu: `docker start ticketumfrage_database`
3. Überprüfen Sie die Datenbankverbindungsparameter in der `.env`-Datei

### Webserverfehler

Wenn der Webserver nicht erreichbar ist:
1. Überprüfen Sie den Status des Webservercontainers: `docker ps -a | grep ticketumfrage_webserver`
2. Falls der Container nicht läuft, starten Sie ihn neu: `docker start ticketumfrage_webserver`
3. Überprüfen Sie die Netzwerkkonfiguration und Port-Weiterleitungen

### Container-Fehler

Wenn Container nicht ordnungsgemäß laufen:
1. Überprüfen Sie alle Container: `docker ps -a`
2. Prüfen Sie die Logs des problematischen Containers: `docker logs ticketumfrage_container_name`
3. Starten Sie den Docker-Stack neu: `docker-compose down && docker-compose up -d`
