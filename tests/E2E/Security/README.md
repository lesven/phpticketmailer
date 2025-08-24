# Security E2E Tests

Diese Tests prüfen das komplette Authentifizierungs- und Autorisierungssystem der Anwendung.

## Test Übersicht

### SecuritySystemE2ETest
**System-Level Security Tests (Database-unabhängig):**

- ✅ **Security Components**: CSRF Manager, Router, Twig verfügbar
- ✅ **Password Hashing**: BCrypt mit verschiedenen Passwort-Typen
- ✅ **Session Handling**: Session-Management und State-Handling
- ✅ **HTTP Request Processing**: GET/POST Request Verarbeitung
- ✅ **Twig Environment**: Template-Rendering für Security-UI
- ✅ **Router Functionality**: URL-Generation für Auth-Routen
- ✅ **Configuration Integrity**: Alle Security-Klassen existieren

### SecuritySubscriberE2ETest
**Route Protection und Access Control:**

- ✅ **Protected Routes Redirect**: Alle geschützten Routen → Login
- ✅ **Public Routes Access**: Login, Monitoring bleiben öffentlich  
- ✅ **Authenticated User Access**: Auth-User kann geschützte Routen nutzen
- ✅ **Sub-Request Handling**: Sub-Requests werden ignoriert
- ✅ **Event Configuration**: SecuritySubscriber ist korrekt konfiguriert
- ✅ **Monitoring Endpoints**: Health-Checks funktionieren ohne Auth

## Getestete Security Features

### 🔐 **Authentifizierung**
- Session-basierte Authentifizierung
- BCrypt Password Hashing
- Default Password Setup ("geheim")
- Passwort-Validierung (min. 8 Zeichen)

### 🛡️ **Autorisierung**
- SecuritySubscriber mit Route Protection
- Redirect zu Login bei unautorisierten Zugriffen
- Öffentliche Monitoring-Endpunkte

### 🔒 **Session Management**
- Session Persistence über mehrere Requests
- Logout löscht Session
- Double-Login Protection

### ⚡ **CSRF Protection**
- Token-basierter Schutz für Login
- Token-basierter Schutz für Passwort-Änderung
- Fehlerbehandlung bei ungültigen Tokens

## Tests ausführen

### Alle Security E2E Tests
```bash
./vendor/bin/phpunit --configuration phpunit-e2e.xml --testsuite="Security E2E Tests"
```

### Einzelne Test-Klassen
```bash
# Haupt-Workflow Tests
./vendor/bin/phpunit tests/E2E/Security/SecurityWorkflowE2ETest.php

# Integration Tests
./vendor/bin/phpunit tests/E2E/Security/AuthenticationIntegrationE2ETest.php
```

## Test Szenarien

### 🎯 **Login Flow**
1. Unauthenticated User → Redirect zu /login
2. Falsches Passwort → Error Message
3. Korrektes Passwort → Redirect zu Dashboard
4. Authentifizierter Zugriff auf geschützte Routen

### 🎯 **Logout Flow**
1. Authentifiziert → Logout → Redirect zu /login
2. Geschützte Route nach Logout → Redirect zu /login

### 🎯 **Password Change Flow**
1. Login → Passwort-Änderung mit aktueller/neuer Validierung
2. Logout → Login mit neuem Passwort
3. Alter Passwort funktioniert nicht mehr

### 🎯 **Security Flow**
1. CSRF Token Validierung
2. Session Persistence
3. Unauthorized Access Protection
4. Public Route Access

## Voraussetzungen

- **Docker Container**: MySQL und PHP Container müssen laufen
- **Test-Datenbank**: `ticket_mailer_db_test` mit Berechtigungen
- **Clean State**: Tests räumen nach sich auf

## Troubleshooting

### Database Connection Error
```bash
# Test-DB anlegen
docker exec -it <mysql-container> mysql -u root -p
CREATE DATABASE IF NOT EXISTS ticket_mailer_db_test;
GRANT ALL PRIVILEGES ON ticket_mailer_db_test.* TO 'ticketuser'@'%';
```

### Test Isolation
Jeder Test:
- Löscht existing AdminPassword Entities
- Erstellt fresh Test-Passwort
- Räumt in tearDown() auf

Diese Tests stellen sicher, dass dein **komplettes Auth-System** funktioniert! 🔒