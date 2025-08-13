# GitHub Copilot Instructions - Ticketumfrage-Tool

## Projektübersicht

Das Ticketumfrage-Tool ist eine Symfony-Anwendung zum automatisierten Versand von Zufriedenheitsanfragen per E-Mail nach Ticketabschluss. Die Anwendung verarbeitet CSV-Dateien mit Ticket-Informationen und sendet personalisierte E-Mails an die entsprechenden Benutzer.

### Hauptfunktionen
- CSV-Upload mit Ticket-Informationen
- Automatischer E-Mail-Versand mit SMTP-Konfiguration
- Benutzer-E-Mail-Verwaltung
- Anpassbares E-Mail-Template
- Test- und Live-Modus
- Versandprotokollierung mit Duplikatsprüfung
- Passwortschutz und Systemüberwachung

## Code-Stil & Konventionen

### Dokumentation
- **Deutsche Kommentare** für alle Klassen, Methoden und komplexe Logik
- **Englische Variablen/Methoden-Namen** (PSR-12 Standard)
- **Deutsche Labels/Messages** für User-Interface
- **Ausführliche DocBlocks** auf Deutsch mit Parameterbeschreibungen

```php
/**
 * Baut das Formular mit seinen Feldern und Validierungsregeln auf
 *
 * Das Formular enthält ein Feld zum Hochladen einer CSV-Datei und
 * eine Checkbox für den Testmodus. Die Datei wird auf gültige MIME-Typen
 * und maximale Größe validiert.
 * 
 * @param FormBuilderInterface $builder Der Formular-Builder
 * @param array $options Optionen für das Formular
 */
public function buildForm(FormBuilderInterface $builder, array $options)
```

### Coding Standards
- **PSR-12** Standard für PHP Code-Formatierung
- **Konsistente Einrückung** und Formatierung
- **Aussagekräftige Variablennamen** auf Englisch
- **Inline-Kommentare** für komplexe Logik auf Deutsch

## Architektur-Prinzipien

### Repository Pattern
- **Repository Pattern bevorzugt** für Datenbankzugriffe
- **Custom Repository-Methoden** für komplexe Abfragen
- **Keine direkten Entity Manager-Calls** in Controllern

```php
// Beispiel: Custom Repository-Methoden
public function findExistingTickets(array $ticketIds): array
public function findRecentEmails(int $limit = 10): array
```

### Service-Orientierte Architektur
- **Services für Business Logic** (EmailService, CsvValidationService, etc.)
- **Controller bleiben schlank** - nur Request/Response handling
- **Dependency Injection** über Constructor
- **Services sind testbar** und wiederverwendbar

```php
// Controller - nur Request/Response
public function sendEmails(Request $request): Response
{
    $sentEmails = $this->emailService->sendTicketEmailsWithDuplicateCheck($ticketData, $testMode, $forceResend);
    return $this->render('csv_upload/results.html.twig', ['sentEmails' => $sentEmails]);
}
```

## Testing-Strategien

### PHPUnit Testing
- **Unit Tests** für Services und Business Logic
- **Integration Tests** für Controller-Funktionalität
- **Test-Struktur parallel** zur Quellcode-Struktur
- **Mocking** für externe Abhängigkeiten

```php
// Beispiel Test-Struktur
class EmailNormalizerTest extends TestCase
{
    private EmailNormalizer $emailNormalizer;

    protected function setUp(): void
    {
        $this->emailNormalizer = new EmailNormalizer();
    }

    public function testNormalizeStandardEmail(): void
    {
        $result = $this->emailNormalizer->normalizeEmail('test@example.com');
        $this->assertEquals('test@example.com', $result);
    }
}
```

### Testing-Prinzipien
- **Tests für neue Features** immer schreiben
- **Fokus auf Service-Layer** Testing
- **Arrange-Act-Assert** Pattern verwenden
- **Aussagekräftige Testnamen** auf Englisch

## Error Handling

### Strukturierte Fehlerbehandlung
- **ErrorHandlingService** für zentrale Fehlerbehandlung
- **Custom Exceptions** wie `TicketMailerException`, `EmailSendingException`
- **Logging und User-Feedback** kombinieren
- **Flash Messages** für Benutzer-Feedback

```php
// Custom Exception mit Context
throw new TicketMailerException(
    "Ungültige E-Mail-Adresse für Benutzer '{$username}': " . $e->getMessage(),
    'validation_error'
);

// Service Usage
$this->errorHandlingService->handleTicketMailerException($exception, $context);

// Controller Flash Messages
$this->addFlash('error', $e->getUserMessage());
$this->addFlash('success', 'E-Mails wurden erfolgreich versendet');
```

## Frontend/UI Guidelines

### Bootstrap & Corporate Design
- **Bootstrap 5** für konsistentes Styling
- **ARZ Haan AG Corporate Design** mit `arz-style.css`
- **Responsive Design** für mobile Nutzung
- **Konsistente Form-Styling** mit Bootstrap-Klassen

```php
// Bootstrap-Styling in Forms
'attr' => ['class' => 'form-control'],           // Input-Felder
'attr' => ['class' => 'form-check-input'],       // Checkboxen
'attr' => ['class' => 'btn btn-primary'],        // Buttons
```

### Twig Templates
- **Klare Template-Struktur** mit Vererbung
- **Komponenten-basiertes Design**
- **Flash Messages** für User-Feedback
- **ARZ Corporate Assets** verwenden

```twig
<link href="{{ asset('css/arz-style.css') }}" rel="stylesheet">
<img src="{{ asset('images/arz-logo.svg') }}" alt="ARZ Logo">
```

## Deployment & Environment

### Docker-First Development
- **Docker-compose** für lokale Entwicklung
- **Multi-Platform Support** (Intel/AMD und ARM)
- **Environment-spezifische Konfiguration** über `.env.local`
- **Automatisierte Deployment-Scripts**

```bash
# Docker Development
docker compose up -d
docker exec -it ticketumfrage_php composer install

# ARM Support (Raspberry Pi)
DOCKER_PLATFORM=linux/arm64v8 docker compose up -d
```

### Monitoring & Health-Checks
- **Zabbix-Integration** für Systemüberwachung
- **Health-Check Endpoints** implementieren
- **Logging** für Debugging und Monitoring
- **Automated Scripts** für Deployment

## Business Logic Patterns

### CSV-Verarbeitung
- **Spezialisierte Services** für CSV-Handling
- **Validierung** und Fehlerbehandlung
- **Duplikatsprüfung** mit `forceResend`-Option
- **Batch-Processing** für Performance

```php
// CSV-Processing mit Validierung
$this->csvValidationService->validateCsvRow($row, $lineNumber);

// Duplikatsprüfung
$existingTickets = $this->emailSentRepository->findExistingTickets($ticketIds);
```

### E-Mail-Versand
- **Test-/Live-Modus** Unterstützung
- **SMTP-Konfiguration** über Admin-Interface
- **Template-System** mit Platzhaltern
- **Fehlerbehandlung** und Retry-Logik

```php
// E-Mail-Versand mit allen Features
public function sendTicketEmailsWithDuplicateCheck(array $ticketData, bool $testMode = false, bool $forceResend = false): array
```

### Outlook-Integration
- **Email-Normalisierung** für Outlook-Format
- **Copy-Paste freundlich** für Benutzer
- **Validierung** nach Normalisierung

```php
// Outlook-Format zu Standard-Email
$normalizedEmail = $this->emailNormalizer->normalizeEmail($emailInput);
```

## Entwicklungs-Workflow

### Neue Features entwickeln
1. **Service-Layer** zuerst implementieren
2. **Unit Tests** schreiben
3. **Controller-Integration** mit schlanker Logik
4. **Template/Frontend** anpassen
5. **Integration Tests** hinzufügen

### Code-Review Kriterien
- **Deutsche Dokumentation** vollständig
- **Error Handling** implementiert
- **Tests** vorhanden
- **Bootstrap-Styling** konsistent
- **Service-Architektur** befolgt

## Wichtige Komponenten

### Kern-Services
- `EmailService` - E-Mail-Versand und Template-Processing
- `CsvValidationService` - CSV-Validierung und -Verarbeitung
- `EmailNormalizer` - Outlook-Format-Normalisierung
- `ErrorHandlingService` - Zentrale Fehlerbehandlung

### Entitäten
- `User` - Benutzer-E-Mail-Zuordnungen
- `EmailSent` - Versandprotokoll
- `SMTPConfig` - SMTP-Konfiguration
- `CsvFieldConfig` - CSV-Feldkonfiguration

### Controller-Struktur
- `CsvUploadController` - CSV-Upload und -Verarbeitung
- `EmailLogController` - Versandprotokoll
- `SMTPConfigController` - SMTP-Konfiguration
- `MonitoringController` - Health-Checks

## Spezielle Anforderungen

### CSV-Verarbeitung
- **Konfigurierbare Spalten** über `CsvFieldConfig`
- **Validierung** auf mehreren Ebenen
- **Duplikatsprüfung** innerhalb CSV und gegen Datenbank
- **Batch-Processing** für Performance

### E-Mail-Template-System
- **Platzhalter-Ersetzung** ({{ticketId}}, {{username}}, etc.)
- **HTML/Text-Templates** unterstützt
- **Testmodus-Hinweise** automatisch hinzufügen
- **Corporate Design** Integration

### Monitoring & Observability
- **Health-Check-Endpunkte** für Zabbix
- **Strukturiertes Logging** mit Context
- **Performance-Monitoring** für E-Mail-Versand
- **Error-Tracking** mit Details

Diese Instructions helfen GitHub Copilot dabei, Code zu generieren, der den Projektstandards entspricht und sich nahtlos in die bestehende Architektur einfügt.
