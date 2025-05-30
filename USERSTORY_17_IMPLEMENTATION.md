# User Story 17 - Implementierung: CSV-Felder konfigurierbar machen

## Übersicht
Die User Story 17 wurde erfolgreich implementiert. Administratoren können jetzt die CSV-Spalten `ticketId`, `username` und `ticketName` auf andere Namen mappen.

## Implementierte Komponenten

### 1. Entity: CsvFieldConfig
- **Datei**: `src/Entity/CsvFieldConfig.php`
- **Zweck**: Speichert die konfigurierten CSV-Feldnamen
- **Felder**:
  - `ticketIdField` (Standard: "ticketId")
  - `usernameField` (Standard: "username") 
  - `ticketNameField` (Standard: "ticketName")
- **Validierung**: Maximale Länge von 50 Zeichen pro Feld

### 2. Repository: CsvFieldConfigRepository
- **Datei**: `src/Repository/CsvFieldConfigRepository.php`
- **Methoden**:
  - `getCurrentConfig()`: Holt aktuelle Konfiguration oder erstellt Standardkonfiguration
  - `saveConfig()`: Speichert Konfiguration

### 3. Form: CsvFieldConfigType
- **Datei**: `src/Form/CsvFieldConfigType.php`
- **Felder**: Drei Textfelder für die Spaltennamen mit Platzhaltern und Validierung

### 4. Erweiterte Formulare
- **CsvUploadType** wurde erweitert um das eingebettete `CsvFieldConfigType`
- Die CSV-Konfiguration wird direkt auf der Upload-Seite angezeigt und kann bearbeitet werden

### 5. Controller-Anpassungen
- **CsvUploadController** lädt und speichert die CSV-Konfiguration
- Übergibt die Konfiguration an den CsvProcessor

### 6. Service-Anpassungen
- **CsvProcessor** verwendet jetzt die konfigurierbaren Feldnamen statt fester Werte
- Unterstützt beliebige Spaltennamen für die drei erforderlichen Felder

### 7. Template-Anpassungen
- **upload.html.twig** zeigt die Konfigurationsfelder an
- Dynamische Anzeige der aktuellen Spaltennamen in der Hilfe

### 8. Migration
- **Version20250530120000**: Erstellt die `csv_field_config` Tabelle mit Standardwerten

## Verwendung

1. **CSV-Upload-Seite aufrufen**: `/upload`
2. **Spaltennamen konfigurieren**: In den drei Feldern können die CSV-Spaltennamen angepasst werden
3. **Leer lassen für Standard**: Wenn ein Feld leer bleibt, wird der Standardwert verwendet
4. **CSV-Datei hochladen**: Die Datei muss die konfigurierten Spaltennamen enthalten

## Beispiel

Wenn Sie folgende CSV-Datei haben:
```csv
ticket_nummer,benutzer,ticket_titel
12345,mueller,Anmeldeproblem
12346,schmidt,Druckerfehler
```

Dann konfigurieren Sie:
- Ticket-ID Spaltenname: `ticket_nummer`
- Benutzername Spaltenname: `benutzer`
- Ticket-Name Spaltenname: `ticket_titel`

## Erfüllte Akzeptanzkriterien

✅ **Konfiguration auf CSV-Upload-Seite sichtbar**: Die drei Konfigurationsfelder sind prominent auf der Upload-Seite platziert

✅ **Default-Werte**: Standardwerte sind "ticketId", "username", "ticketName"

✅ **Fallback auf Default**: Wenn ein Feld leer ist, wird der Standardwert verwendet

✅ **Maximale Länge 50 Zeichen**: Validierung verhindert längere Eingaben

## Technische Details

- **Datenbank**: Neue Tabelle `csv_field_config` mit Auto-Increment ID
- **Validierung**: Symfony-Validierung für maximale Länge
- **Fallback-Logic**: In der Entity implementiert mit Null-Coalescing
- **Session-unabhängig**: Konfiguration wird persistent in der Datenbank gespeichert
