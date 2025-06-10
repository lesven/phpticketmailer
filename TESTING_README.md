# Testscripts für Ticketumfrage-Tool

Diese Sammlung von Testscripts ermöglicht automatisierte Regressionstests für das Ticketumfrage-Tool, insbesondere für die Userstory 20 (Versandprotokoll).

## ✅ Status

**ALLE TESTS ERFOLGREICH ABGESCHLOSSEN** 🎉

- ✅ **Userstory 20 Implementation**: Vollständig implementiert und getestet
- ✅ **Lokale Tests**: Alle Tests laufen erfolgreich
- ✅ **Docker Tests**: Alle Tests laufen erfolgreich in Docker-Umgebung
- ✅ **Datenbank Tests**: Strukturprüfungen und Performance-Tests bestehen
- ✅ **Regression Tests**: Vollständige Validierung aller Funktionen

Das Ticketumfrage-Tool mit Userstory 20 (Versandprotokoll) ist **bereit für den Produktionseinsatz**.

## 📋 Verfügbare Testscripts

### 1. `run_all_tests.sh` - Master-Testscript
Das Hauptscript, das alle verfügbaren Tests orchestriert.

**Verwendung:**
```bash
# Schnelle Tests (Standard)
./run_all_tests.sh

# Alle Tests
./run_all_tests.sh --full

# Nur Datenbanktests
./run_all_tests.sh --database-only

# Hilfe anzeigen
./run_all_tests.sh --help
```

### 2. `test_versandprotokoll.sh` - Userstory 20 Tests
Umfassende Tests für das Versandprotokoll (Userstory 20).

**Getestete Funktionalitäten:**
- ✅ Datenbankverbindung und Migrationen
- ✅ Route-Registrierung (`/versandprotokoll`)
- ✅ Login-Funktionalität
- ✅ Versandprotokoll-Seite lädt korrekt
- ✅ Suchfunktion nach Ticket-ID
- ✅ Wildcard-Suche
- ✅ Status-Anzeige (sent, error, warning)
- ✅ Testmodus vs. Live-Modus Anzeige
- ✅ Navigation und Menüintegration
- ✅ Performance-Check
- ✅ Datenintegrität

**Verwendung:**
```bash
./test_versandprotokoll.sh
```

### 3. `test_database.sh` - Erweiterte Datenbanktests
Tiefgreifende Tests der Datenbankstruktur und -funktionalität.

**Getestete Bereiche:**
- ✅ Tabellenexistenz (emails_sent, users, admin_password, smtpconfig)
- ✅ Spaltenstruktur und Datentypen
- ✅ Datenbank-Constraints (UNIQUE, NOT NULL)
- ✅ CRUD-Operationen
- ✅ Migration-Konsistenz
- ✅ SSL-Verifizierung (verify_ssl Spalte)
- ✅ Performance kritischer Abfragen
- ✅ Datenintegrität
- ✅ E-Mail-Validierung

**Verwendung:**
```bash
./test_database.sh
```

## 🚀 Schnellstart

1. **Stelle sicher, dass die Umgebung läuft:**
   ```bash
   # Datenbank starten (falls Docker verwendet wird)
   docker-compose up -d mysql
   
   # Oder stelle sicher, dass MariaDB läuft
   ```

2. **Führe alle Tests aus:**
   ```bash
   ./run_all_tests.sh
   ```

3. **Bei Fehlern, führe spezifische Tests aus:**
   ```bash
   # Nur Datenbanktests
   ./test_database.sh
   
   # Nur Versandprotokoll-Tests
   ./test_versandprotokoll.sh
   ```

## 📊 Testdaten

Die Testscripts erstellen automatisch Testdaten:

### Versandprotokoll-Tests:
- `TEST-001` bis `TEST-004` - Verschiedene E-Mail-Status
- Automatisches Cleanup nach Tests

### Datenbank-Tests:
- `DBTEST-001` bis `DBTEST-050` - Performance-Tests
- `dbtest_*` Benutzer - Constraint-Tests
- Automatisches Cleanup nach Tests

## 🔧 Voraussetzungen

- **PHP** (>= 8.1)
- **curl** für HTTP-Tests
- **Symfony Console** für Datenbank-Operationen
- **MariaDB/MySQL** Datenbank
- **Laufende Anwendung** oder Möglichkeit, Development Server zu starten

## 🎯 CI/CD Integration

Diese Scripts können in CI/CD-Pipelines integriert werden:

```yaml
# Beispiel für GitHub Actions
- name: Run Regression Tests
  run: |
    cd phpticketmailer
    ./run_all_tests.sh --full
```

```bash
# Beispiel für Jenkins/GitLab CI
script:
  - cd phpticketmailer
  - ./run_all_tests.sh --full
```

## 🐛 Fehlerbehandlung

### Häufige Probleme:

1. **"Datenbankverbindung fehlgeschlagen"**
   - Überprüfe `.env` Datei
   - Stelle sicher, dass MariaDB läuft
   - Teste: `php bin/console dbal:run-sql "SELECT 1"`

2. **"Server läuft nicht"**
   - Port 8000 bereits belegt: `lsof -i :8000`
   - Firewall blockiert: Temporär deaktivieren
   - PHP nicht gefunden: `which php`

3. **"Migration fehlgeschlagen"**
   - Führe Migrationen manuell aus: `php bin/console doctrine:migrations:migrate`
   - Überprüfe Datenbankberechtigungen

4. **"Login fehlgeschlagen"**
   - Standardpasswort "geheim" verwenden
   - Admin-Passwort zurücksetzen: `php bin/console app:reset-password`

## 📝 Logs und Debugging

### Detaillierte Ausgabe:
```bash
# Mehr Details bei Fehlern
set -x  # In Script einfügen für Debug-Modus
./test_versandprotokoll.sh
```

### Log-Dateien:
- Symfony Logs: `var/log/dev.log`
- PHP Error Log: Abhängig von PHP-Konfiguration
- Datenbank Logs: Abhängig von MariaDB-Konfiguration

## 🔄 Wartung

### Regelmäßige Ausführung:
```bash
# Cron-Job für tägliche Tests (Beispiel)
0 2 * * * cd /path/to/phpticketmailer && ./run_all_tests.sh --quick > /var/log/ticketmailer_tests.log 2>&1
```

### Script-Updates:
- Scripts sind modular aufgebaut
- Neue Tests können einfach hinzugefügt werden
- Folge dem bestehenden Pattern für Konsistenz

## 📈 Metriken

Die Tests messen automatisch:
- ⏱️ **Antwortzeiten** (< 2 Sekunden erwartet)
- 📊 **Datenbank-Performance** (< 100ms für Standard-Queries)
- 🔍 **Speicherverbrauch** (wird angezeigt)
- ✅ **Erfolgsraten** (100% erwartet)

## 🤝 Beitragen

Beim Hinzufügen neuer Features oder Bugfixes:

1. **Erstelle entsprechende Tests**
2. **Aktualisiere bestehende Tests** falls nötig
3. **Teste deine Änderungen:**
   ```bash
   ./run_all_tests.sh --full
   ```
4. **Dokumentiere neue Tests** in dieser README

---

**💡 Tipp:** Führe Tests vor jedem Deployment aus, um Regressionen zu vermeiden!
