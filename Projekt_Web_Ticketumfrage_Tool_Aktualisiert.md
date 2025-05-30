# Projektbeschreibung: Web-Anwendung für automatischen E-Mail-Versand zur Ticketzufriedenheit (Symfony)

## 1. Zusammenfassung
Dieses Dokument beschreibt die Anforderungen und das Design einer Web-Anwendung, die täglich eine CSV-Datei mit Tickets verarbeitet, für jedes Ticket eine Zufriedenheitsanfrage generiert und per E-Mail an den jeweiligen Ersteller verschickt. Die Anwendung verwaltet die Zuordnung von Usernamen zu E-Mail-Adressen über ein webbasiertes Interface und ermöglicht die manuelle Pflege dieser Daten. Das System unterstützt einen Testmodus, eine Voransicht der E-Mails sowie den Upload und Download des E-Mail-Templates.

## 2. Technologiestack
- **Programmiersprache:** PHP
- **Framework:** Symfony
- **Frontend:** Twig + HTML/CSS (optional JS für interaktive Komponenten)
- **Datenbank:** MariaDb

## 3. Hauptfunktionen
- **CSV-Upload:** Nutzer lädt täglich eine CSV-Datei mit Ticketinformationen hoch.
- **Validierung:** Nur vollständige Zeilen werden verarbeitet. Ungültige Zeilen werden nach dem Upload angezeigt.
- **E-Mail-Zuordnung:** Nach dem Upload werden unbekannte Usernamen aufgelistet und müssen über ein Webformular ergänzt werden.
- **E-Mail-Verwaltung:** Bestehende User-E-Mail-Zuordnungen können im Browser bearbeitet oder gelöscht werden.
- **Mail-Versand:** Für jedes gültige Ticket wird eine E-Mail mit Zufriedenheitsanfrage per SMTP versendet.
- **Testmodus:** Per Checkbox beim Upload aktivierbar. Alle Mails gehen an eine definierte Testadresse.
- **Versandübersicht:** Nach dem Versand wird eine Liste mit Ticket-ID, Empfängeradresse und Versandstatus angezeigt.
- **Template-Verwaltung:** Download und Upload des E-Mail-Templates über das Admininterface.

## 4. Benutzeroberfläche
- **Dashboard:** Übersicht über letzte Uploads und Versandaktionen.
- **CSV-Upload:** Formular mit Spaltenzuordnung und Testmodus-Checkbox.
- **Unbekannte User:** Liste mit fehlenden E-Mails zur Nachpflege nach Upload.
- **E-Mail-Zuordnungen:** Tabelle mit Bearbeiten- und Löschen-Funktion (Paginierung, keine Suche).
- **Versandübersicht:** Anzeige der Versanddetails nach Abschluss.
- **Template-Verwaltung:** Download und Upload eines `.txt` Templates.

## 5. Konfiguration (über .env oder Admin-Interface)
- SMTP-Host, Port, Benutzername, Passwort, TLS/SSL
- Standard-Senderadresse und Absendername
- Basis-URL für Ticketlinks (z. B. `https://www.ticket.de`)
- Betreff-Vorlage: z. B. `Ihre Rückmeldung zu Ticket {{ticketId}}`
- Testmodus-Empfängeradresse
- Konfiguration der CSV-Spaltennamen (`ticketId`, `username`, `ticketName`)

## 6. Datenbankstruktur (Beispiel)
- **users**: `id`, `username`, `email`
- **emails_sent**: `id`, `ticket_id`, `username`, `email`, `subject`, `status`, `timestamp`
- **config**: key-value-basierte Systemkonfiguration (optional)

## 7. User Stories
### User Story 1: E-Mail-Adresse für Benutzer ermitteln und speichern
*Als Web-Anwendung möchte ich nach dem CSV-Upload eine Liste aller User anzeigen, für die keine E-Mail-Adresse bekannt ist, damit diese ergänzt werden müssen, bevor ein Versand erfolgen kann.*
**Akzeptanzkriterien:**
- Nach dem Upload wird eine Liste fehlender Adressen angezeigt.
- E-Mails können direkt im Browser ergänzt werden.
- Speichern erfolgt in der Datenbank.
- Nur gültige E-Mails sind erlaubt (Validation im UI).

### User Story 2: Ticketlink aus Ticket-ID generieren
*Als Web-Anwendung möchte ich für jedes Ticket einen Link nach dem Format `www.ticket.de/{ticketId}` generieren, damit ich diesen in die E-Mail-Vorlage einsetzen kann.*
**Akzeptanzkriterien:**
- Der Link wird auf Basis der konfigurierten Basis-URL erzeugt.
- Der Link wird **nur in der E-Mail** verwendet.

### User Story 3: E-Mail-Template mit Ticketdaten befüllen
*Als Web-Anwendung möchte ich ein Template laden und Platzhalter wie `{{ticketLink}}` und `{{ticketName}}` ersetzen, damit daraus personalisierte E-Mails generiert werden.*
**Akzeptanzkriterien:**
- Das Template liegt als Datei auf dem Server.
- Download und Upload des Templates sind über das Interface möglich.

### User Story 4: E-Mail per SMTP versenden
*Als Web-Anwendung möchte ich die E-Mails direkt über einen SMTP-Server versenden, damit Benutzer automatisiert kontaktiert werden können.*
**Akzeptanzkriterien:**
- SMTP-Daten sind konfigurierbar.
- Nach dem Versand erscheint eine Übersicht mit Ticket-ID, Empfänger und Status.

### User Story 5: Testmodus für E-Mail-Versand
*Als Administrator möchte ich beim CSV-Upload einen Testmodus aktivieren können, damit alle E-Mails an eine Testadresse gehen.*
**Akzeptanzkriterien:**
- Checkbox beim Upload aktiviert den Testmodus.
- Die Mails gehen an die Testadresse.
- Der ursprünglich vorgesehene Empfänger wird im Mailtext erwähnt.

### User Story 6: Verwaltung der gespeicherten E-Mail-Zuordnungen
*Als Administrator möchte ich gespeicherte Zuordnungen im Browser anzeigen, bearbeiten und löschen können,* damit ich flexibel reagieren kann.*
**Akzeptanzkriterien:**
- Tabelle mit Paginierung.
- Bearbeiten und Löschen pro Eintrag möglich.

### User Story 7: Verarbeitung der Ticketliste aus CSV-Datei
*Als Web-Anwendung möchte ich eine CSV-Datei mit vollständigen Ticketdaten verarbeiten, damit daraus automatisiert E-Mails generiert werden können.*
**Akzeptanzkriterien:**
- Nur gültige Zeilen mit vollständigen Daten werden übernommen.
- Ungültige Zeilen werden nach Upload angezeigt und nicht verarbeitet.
### User Story 8: Konfiguration des SMTP Servers über das Frontend
*Als Webanwendung möchte ich die Konfiguration des SMTP Servers im Browser anzeigen und bearbeiten können. Um die Konfiguration zu testen wird beim speichern eine Testemail mit dem BEtreff Test und dem Body Success an eine Email adresse gesendet die der User im Formular eingeben muss.*
**Akzeptanzkriterien:**
- nur gültige Werte werden übernommen
- ungültige Werte werden angezeigt
- Username und Passwort sind nicht Pflicht
- TLS ist eine Checkbox
- die aktuelle Konfigguration wird in den feldern angezeigt

### User Story 9: Export aller Nutzer in eine CSV Datei
*Als Webanwendung möchte ich alle ekanntenNutzer in eine CSV Datei exportieren können*
**Akzeptanzkriterien:**
- der Export soll innerhalb der Benutzerverwaltung möglich sein
- es sollen ID, Name und Emailadresse exportiert werden

### User Story 10: Import von Nutzern aus einer CSV Datei
*Als Webanwendung möchte ich eine CSV Datei mit Usern importieren können. die CSV Datei hat die Spalten email, und benutzername. ICh will eentscheiden könne nob ich vorher alle Benutzer löschen möchte oder ob die User zusätzlich hinzugefügt werden sollen. ICh möcht das Duplikate herausgefiltert werden*
**Akzeptanzkriterien:**
- der Import soll innerhalb der Benutzerverwaltung möglich sein
- es sollen ID, Name und Emailadresse importiert werden
- checkbox oder Benutzerdatenbank vorher gelleert werden soll

### User Story 11: Suche nach Benutzernamen in der Benutzerverwaltung
*Als Webanwendung möchte ich dass ich nach dem benutzernamen suchen kann damit ich schneller die Nutzer finde*
**Akzeptanzkriterien:**
- Eingabefeld einzeilig für benutzernamen
- es werden auch angefangen Nutzernamen gefunden, also wildcard dahinter *
- groß und Kleinschreibung soll ignoriert werden

### User Story 12: Benutzerverwaltung alle spalten sollen sortierbar sein
*Als Webanwendung möchte ich dass ich die spalten in der benutzertabelle sortieren kann*
**Akzeptanzkriterien:**
- auf und absteigend sortieren soll möglich sein
- jede spalte soll sortierbar sein
- ich will passende piktogramme für die sortierung an den spalten haben

### User Story 13: Die Seite soll mit einem Passwort geschützt sein
*Als Administrator möchte ich dass die seite und ihre FUnktionalitäten nur zugänglich sind wenn man sich mit einem Passwort authentifiziert hat. Sobald man authentifiziert ist gibt es keinerlei unterschiede in den Berechtigungen*
**Akzeptanzkriterien:**
- Passwort darf nicht leer sein und muss aus mindestens 8 Zeichen bestehen
- Das Passwort wird verschlüsselt gespeichert
- das Standardpasswort lautet "geheim" wenn kein anderes Passwort gesetzt wurde
- es gibt einen Menüpunkt der Passwort ändern heißt

### User Story 14: Heutiges Datum +7 Tage in die Email als Variable mit einbauen
*Als Administrator möchte ich als neue Variable das aktuelle Datum in der Email Vorlage als Variable nutzen können. Damit möchte ich dem User sagen wann ich ihm die Mail geschrieben und ich möchte ihn bitten dsas er die Umfrage bis zu diesem Datum beantwortet*
**Akzeptanzkriterien:**
- Das Datum soll im deutschen Format sein Tag, Monat und vierstelliges Jahr
- Der Monat soll ausgeschrieben sein
- Keine Uhrzeit

### User Story 15: Ich möchte das E-Mail Temaplate in einem WYSIWYG Editor bearbeiten könne ###
*Als Administrator mchte ich zusätzlich zum Import und Export das Template über einen eifnachen WYSIWYG Editor live direkt im Frontend bearbeiten können.*
**Akzeptanzkriterien:**
- Fett, kursiv und Unterstrichen sollen möglich sein
- links zu webseiten sollen möglich sein
- Das Tempalte soll passend als HTML Datei gespeichert werden und für den Import und Export genutzt werden

### Userstory 16: Das Ticketmail Tool soll CI Konform zur ARZHaan AG sein
*Als Marketing Verantwortlicher möcht eich dass der Ticketmail CI Konform zu unserer Homepage arz.de ist* 
** Akzeptanzkriterien:**
- die Farben und die Schrifart soll übernommenen werden von arz.de
- das Logo soll übernommen werden

### Userstory 17: Die CSV Felder sollen konfigurierbar sein ###
*Als Administrator möchte ich die Felder  ticketId, username und ticketName beliebig auf andere Namen mappen können um so einem veränderten Ticket System uund Exporten gewappnet zu sein*
** Akzeptanzkriterien ** 
- KOnfiguration soll auf der Seite des CSV Uploads sichtbar und konfigurierbar sein
- Default wert sollen die bereits bekannten Werte sein
- Wenn nichts eingetragen ist, soll er die default werte nehmen
- maximal länge sollen 50 Zeichen sein

### User Story 18: Vermeidung doppelter Ticket-Versendungen ###
*Als Administrator möchte ich verhindern, dass für dieselbe Ticket-ID mehrfach E-Mails verschickt werden, um versehentlichen Doppelversand zu vermeiden. Ich möchte jedoch beim Upload festlegen können, ob ich den Versand trotzdem erzwingen möchte.*
** Akzeptanzkriterien: **
- Beim CSV-Upload gibt es eine Checkbox „Erneut versenden, wenn Ticket bereits verarbeitet wurde“.
- Ist die Checkbox nicht aktiviert, wird jede Zeile der CSV-Datei geprüft:
- Wurde die Ticket-ID bereits in der Datenbank (emails_sent) gefunden, wird keine E-Mail verschickt.
- Die betroffenen Einträge erscheinen in der Versandübersicht mit dem Status:
„Nicht versendet – Ticket bereits verarbeitet am <Datum>“.
-Ist die Checkbox aktiviert, wird der Versand für alle Zeilen durchgeführt, auch wenn die Ticket-ID schon bekannt ist.
- die Prüfung erfolgt rein auf Basis der ticket_id.
- Mehrfache Vorkommen derselben ticket_id innerhalb derselben CSV-Datei werden nicht blockiert – es wird jeweils nur die erste verschickt, die weiteren gelten als bereits verarbeitet (bei deaktiviertem Force-Flag).
- Die Entscheidung gilt global pro Upload – keine Einzelfallsteuerung notwendig.


### User Story 19: Paginierung der Benutzerliste
*Als Benutzer der Anwendung möchte ich die Liste der bekannten Nutzer mit ihren E-Mail-Adressen seitenweise angezeigt bekommen, damit ich auch bei vielen Einträgen die Übersicht behalte und die Seite schnell geladen wird.*
#### Akzeptanzkriterien:
- Die Benutzerliste ist serverseitig paginiert.
- Es werden maximal **15 Nutzer pro Seite** angezeigt.
- Die Navigation erfolgt über **„Zurück“** und **„Weiter“**-Buttons.
- Beim Öffnen der Benutzerliste wird immer **Seite 1** geladen.
- Die Paginierung ist **nicht aktiv**, wenn eine **Suchanfrage** im Suchfeld eingegeben wurde – dann wird die komplette Treffermenge auf einmal angezeigt.
