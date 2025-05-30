# Ticket-Basis-URL Konfiguration Implementation

## Übersicht
Die `APP_TICKET_BASE_URL` wurde erfolgreich von der .env-Datei in die SMTP-Konfigurationsseite der Datenbank migriert. Benutzer können jetzt die Ticket-Basis-URL direkt im Admin-Interface bearbeiten.

## Implementierte Änderungen

### 1. SMTPConfig Entity (`src/Entity/SMTPConfig.php`)
- **Neues Feld hinzugefügt**: `ticketBaseUrl`
- **Validierung**: NotBlank und URL-Validierung
- **Getter/Setter**: `getTicketBaseUrl()` und `setTicketBaseUrl()`

### 2. SMTPConfigType Form (`src/Form/SMTPConfigType.php`)
- **Neues Formularfeld**: `ticketBaseUrl` mit TextType
- **Placeholder**: "z.B. https://www.ticket.de"
- **Hilfetext**: "Diese URL wird für die Generierung von Ticket-Links in E-Mails verwendet"

### 3. SMTPConfigController (`src/Controller/SMTPConfigController.php`)
- **Standardwert**: `https://www.ticket.de` für neue Konfigurationen
- **Integration**: Vollständig in den bestehenden Speicher- und Testworkflow integriert

### 4. EmailService (`src/Service/EmailService.php`)
- **Priorität**: Nutzt `ticketBaseUrl` aus der Datenbank-Konfiguration
- **Fallback**: Verwendet .env Parameter nur wenn keine DB-Konfiguration vorhanden ist

### 5. Database Migration (`migrations/Version20250530130000.php`)
- **Neue Spalte**: `ticket_base_url VARCHAR(255)` in `smtpconfig` Tabelle
- **Standardwert**: `https://www.ticket.de`
- **Rückgängig-Funktion**: `down()` Methode zum Entfernen der Spalte

### 6. Template (`templates/smtp_config/edit.html.twig`)
- **Platzierung**: Im "Absender-Einstellungen" Bereich
- **Layout**: Vollbreite Eingabefeld für URL

### 7. .env Datei bereinigt
- **Entfernt**: Veraltete MAILER_DSN Konfiguration
- **Hinzugefügt**: Fallback-Parameter für alle E-Mail-Einstellungen
- **Dokumentiert**: Klare Kommentare zur neuen Konfigurationsstrategie

## Funktionsweise

### Priorität der Konfiguration:
1. **Datenbank-Konfiguration** (SMTPConfig Entity) - **Primary**
2. **Fallback-Parameter** (.env Datei) - Nur wenn keine DB-Konfiguration vorhanden

### Admin-Workflow:
1. Benutzer navigiert zu `/smtp-config`
2. Kann alle SMTP-Einstellungen inklusive Ticket-Basis-URL bearbeiten
3. Kann Konfiguration mit Test-E-Mail validieren
4. Ticket-Basis-URL wird sofort in E-Mail-Templates verwendet

## Migration
Um die Änderungen anzuwenden:
```bash
php bin/console doctrine:migrations:migrate
```

## Nutzung
Nach der Migration können Administratoren:
- Die Ticket-Basis-URL im SMTP-Konfigurationsformular bearbeiten
- Die URL wird automatisch in allen E-Mail-Templates für `{{ticketLink}}` verwendet
- Konfiguration mit Test-E-Mail validieren

## Validierung
- **URL-Format**: Symfony URL-Validator stellt sicher, dass nur gültige URLs eingegeben werden
- **Pflichtfeld**: Das Feld darf nicht leer sein
- **Standard**: Neuen Installationen haben `https://www.ticket.de` als Standard

## Backward Compatibility
- ✅ Bestehende Installationen: Fallback auf .env Parameter wenn noch keine DB-Konfiguration
- ✅ Neue Installationen: Standardwert wird automatisch gesetzt
- ✅ Migration: Bestehende Daten bleiben unverändert, nur neue Spalte wird hinzugefügt
