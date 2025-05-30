# User Story 18 Implementation: Vermeidung doppelter Ticket-Versendungen

## Implementierte Änderungen

### 1. CsvUploadType.php
- Neue Checkbox `forceResend` hinzugefügt
- Label: "Erneut versenden, wenn Ticket bereits verarbeitet wurde"
- Standardmäßig deaktiviert (false) zur Duplikatsvermeidung

### 2. EmailSentRepository.php
- `findByTicketId()` - Prüft ob eine Ticket-ID bereits verarbeitet wurde
- `findExistingTickets()` - Batch-Prüfung für mehrere Ticket-IDs

### 3. EmailService.php
- Neue Methode `sendTicketEmailsWithDuplicateCheck()` mit Duplikatsprüfung
- `createSkippedEmailRecord()` für übersprungene E-Mails
- Prüfung auf:
  - Duplikate innerhalb derselben CSV-Datei
  - Bereits in der Datenbank verarbeitete Ticket-IDs
- Status-Meldungen:
  - "Nicht versendet – Mehrfaches Vorkommen in derselben CSV-Datei"
  - "Nicht versendet – Ticket bereits verarbeitet am [Datum]"

### 4. CsvUploadController.php
- Parameter `forceResend` in allen relevanten Methoden weitergegeben
- Upload, unknownUsers und sendEmails Routen erweitert

### 5. Templates aktualisiert
- `upload.html.twig`: Neue Checkbox angezeigt
- `send_result.html.twig`: 
  - Neue Status-Kategorie "Übersprungen" mit gelber Badge
  - Erweiterte Zusammenfassung mit 4 Kategorien
- `dashboard/index.html.twig`: Konsistente Status-Anzeige

### 6. Test-CSV erstellt
- `test_duplicate_tickets.csv` mit Duplikaten zum Testen

## Funktionalität

### Standardverhalten (forceResend = false)
1. Prüfung aller Ticket-IDs gegen die Datenbank
2. Duplikate innerhalb der CSV werden nur einmal versendet
3. Bereits verarbeitete Tickets werden übersprungen
4. Status zeigt Datum der ersten Verarbeitung

### Erzwungener Versand (forceResend = true)
1. Alle E-Mails werden versendet
2. Keine Duplikatsprüfung gegen die Datenbank
3. Duplikate innerhalb der CSV werden trotzdem verhindert

## Status-Meldungen
- **Grün (Erfolg)**: "sent" 
- **Gelb (Übersprungen)**: "Nicht versendet – ..."
- **Rot (Fehler)**: "error: ..."

## Akzeptanzkriterien erfüllt ✓
- ✓ Checkbox für "Erneut versenden" beim CSV-Upload
- ✓ Prüfung gegen emails_sent Tabelle basierend auf ticket_id
- ✓ Status-Meldungen mit Datum der ersten Verarbeitung
- ✓ Globale Entscheidung pro Upload
- ✓ Duplikate innerhalb derselben CSV werden verhindert
- ✓ Nur erste Vorkommen werden versendet bei deaktiviertem Force-Flag
