# End-to-End Tests

Diese E2E Tests testen den kompletten Workflow der Anwendung von CSV Upload bis Email Versand.

## Voraussetzungen

Die E2E Tests benötigen:
1. **Laufende Docker Container** (MySQL, SMTP)
2. **Test-Datenbank Berechtigung** für `ticketuser`
3. **SMTP Server** (MailHog oder ähnlich)

## Datenbank Setup

Erstelle die Test-Datenbank und gib dem User Berechtigungen:

```sql
-- In MySQL Container ausführen
CREATE DATABASE IF NOT EXISTS ticket_mailer_db_test;
GRANT ALL PRIVILEGES ON ticket_mailer_db_test.* TO 'ticketuser'@'%';
FLUSH PRIVILEGES;
```

## Tests ausführen

### Nur Unit/Integration Tests (Standard)
```bash
make test
```

### Nur E2E Tests
```bash
./vendor/bin/phpunit --configuration phpunit-e2e.xml
```

### Alle Tests inklusive E2E
```bash
./vendor/bin/phpunit --configuration phpunit-e2e.xml --testsuite="E2E Tests"
make test
```

## Test Struktur

### Workflow Tests
- `CsvUploadHappyPathE2ETest` - Kompletter CSV → Email Workflow
- Testet HTTP Requests, Datenbank Integration, Email Service

### Service Tests  
- `CsvUploadServiceE2ETest` - Service Layer Integration
- Testet ohne HTTP Layer, direkter Service Aufruf

### Fixtures
- `tests/fixtures/csv/` - Test CSV Dateien
- `valid_users.csv` - 5 gültige Benutzer
- `duplicate_tickets.csv` - Duplikat Tests
- `invalid_emails.csv` - Validierung Tests

## Troubleshooting

### Database Connection Error
```
SQLSTATE[HY000] [1044] Access denied for user 'ticketuser'@'%' to database 'ticket_mailer_db_test'
```
**Lösung:** Test-Datenbank anlegen und Berechtigungen setzen (siehe oben)

### Docker Container nicht erreichbar  
```
SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo for database failed
```
**Lösung:** `docker-compose up -d` und warten bis Container ready

### SMTP Fehler
**Lösung:** MailHog Container starten oder SMTP Tests mit Mock konfigurieren