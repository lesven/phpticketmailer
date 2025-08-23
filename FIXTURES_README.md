# Data Fixtures für Ticketumfrage-Tool

## Übersicht

Das Data Fixtures System stellt Testdaten für das Ticketumfrage-Tool bereit, um die Anwendung einfach testen und entwickeln zu können.

## Fixtures Command

### Installation und Verwendung

```bash
# Fixtures laden (nur wenn noch keine existieren)
php bin/console app:load-data-fixtures

# Fixtures überschreiben (löscht existierende Fixture-Daten)
php bin/console app:load-data-fixtures --force
```

### Generierte Testdaten

Der Fixtures-Befehl erstellt folgende Testdaten:

#### 1. Testbenutzer (10 Stück)
- **Benutzernamen**: `fixtures_user1` bis `fixtures_user10`
- **E-Mails**: `user1@example.com` bis `user10@example.com`
- **Besonderheiten**: Jeder 3. Benutzer ist von Umfragen ausgeschlossen (für Testzwecke)

#### 2. SMTP-Konfiguration
- **Host**: `mailpit` (für lokale Entwicklung)
- **Port**: `1025`
- **Keine Authentifizierung** (username/password: null)
- **TLS**: Deaktiviert
- **SSL-Verifizierung**: Deaktiviert
- **Absender**: `noreply@ticketmailer.local`
- **Absendername**: `Ticket Survey System`
- **Ticket-Basis-URL**: `https://tickets.example.com`

#### 3. CSV-Feldkonfiguration
- **Ticket-ID-Feld**: `Vorgangsschlüssel`
- **Benutzername-Feld**: `Autor`
- **Ticket-Name-Feld**: `Zusammenfassung`

#### 4. E-Mail-Protokolle (15 Stück)
- **Ticket-IDs**: `FIXTURE-001` bis `FIXTURE-015`
- **Status**: Verschiedene (sent, error: SMTP connection failed, error: Invalid email)
- **Testmodus**: Gemischt (true/false)
- **Zeitstempel**: Verteilt über die letzten 30 Tage
- **Empfänger**: Verknüpft mit den Testbenutzern

#### 5. Admin-Passwort
- **Passwort**: `admin123` (gehashed mit PASSWORD_BCRYPT)
- Für den Admin-Login verwendbar

## Verwendung in Tests

Die Fixtures sind so konzipiert, dass sie:

1. **Idempotent** sind - können mehrfach ausgeführt werden
2. **Sicher überschreibbar** sind mit `--force` Flag
3. **Realistische Testszenarien** abdecken
4. **Verschiedene Status** und Konfigurationen repräsentieren

## Entwicklungshinweise

### Fixtures erweitern

Um neue Fixture-Daten hinzuzufügen:

1. Neue Methode in `LoadDataFixturesCommand` erstellen (z.B. `loadNewEntityFixtures()`)
2. Methode in `execute()` aufrufen
3. Entsprechende Tests in `LoadDataFixturesCommandTest` hinzufügen

### Sicherheitshinweise

- **Niemals in Produktion verwenden** - Fixtures sind nur für Entwicklung/Tests gedacht
- Admin-Passwort ist bewusst einfach gewählt - in Produktion sichere Passwörter verwenden
- E-Mail-Adressen sind alle `.example.com` - keine echten E-Mails werden versendet

## Testabdeckung

Das Command ist vollständig getestet:

```bash
# Tests ausführen
php bin/phpunit tests/Command/LoadDataFixturesCommandTest.php
```

Die Tests überprüfen:
- Korrekte Konfiguration des Commands
- Verhalten bei existierenden Fixtures
- Erstellung aller Entity-Typen
- Inhalt der generierten Daten
- Passworrt-Hashing Funktionalität

## Integration mit bestehenden Tests

Die Fixtures sind so benannt (`fixtures_*`, `FIXTURE-*`), dass sie:
- Leicht von echten Daten unterscheidbar sind
- Von bestehenden Testscripts erkannt und bereinigt werden können
- Keine Konflikte mit bestehenden Testdaten verursachen