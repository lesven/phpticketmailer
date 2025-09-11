# GitHub Copilot Instructions - Ticketumfrage-Tool

## Project Overview

This is a Symfony-based email survey tool that automates satisfaction surveys via email after ticket closure. It processes CSV files containing ticket data and sends personalized emails with template placeholders.

**Key Architecture**: Service-oriented design with strict separation of concerns - Controllers handle HTTP, Services contain business logic, Repositories manage data access.

## Core Workflow & Components

### CSV Processing Flow
1. **Upload** (`CsvUploadController`) → **Process** (`CsvUploadOrchestrator`) → **Validate** (`CsvProcessor`)
2. **Unknown Users** → Manual email mapping → **User Creation** (`UserCreator`)
3. **Email Sending** (`EmailService`) with duplicate checking and test/live modes

### Critical Services
- `CsvUploadOrchestrator`: Main workflow coordinator - handles all CSV processing steps
- `SessionManager`: Manages multi-step workflow state between HTTP requests
- `EmailService`: Handles SMTP configuration, template processing, duplicate detection
- `EmailNormalizer`: Converts Outlook format emails ("Name <email@domain.com>") to standard format

### Form Field Naming Convention
**Important**: Username dots (`.`) are converted to underscores (`_`) in HTML form fields. Always use `convertUsernameForHtmlAttribute()` helper when processing form data for usernames containing special characters.

## Development Workflow

### Docker-First Development
```bash
# Start environment
make up              # Start containers
make composer-install # Install dependencies
make migrate         # Run database migrations

# Development commands
make exec-php        # Interactive shell in PHP container
make console ARGS="..." # Run Symfony console commands
make test           # Run PHPUnit tests in container
make cache-clear    # Clear Symfony cache
```

### Key Makefile Targets
- `make fresh`: Complete rebuild (down, build, up, composer, migrate)
- `make logs-php`: Follow PHP container logs for debugging
- `make coverage`: Generate code coverage with Xdebug
- `make recreate-db`: Reset database volume entirely

## Testing Strategy

### Test Structure (Mirror Source)
- **Unit Tests**: Services and business logic (`tests/Service/`)
- **Integration Tests**: Controllers with mocked dependencies (`tests/Controller/`)
- **E2E Tests**: Full workflow tests (`tests/E2E/`) - require running containers

### Test Execution
```bash
make test                    # Standard unit/integration tests
./vendor/bin/phpunit --configuration phpunit-e2e.xml  # E2E tests
```

**Critical**: E2E tests need database permissions and running SMTP service. Always test username edge cases (dots, special chars).

## Code Patterns & Conventions

### German Documentation, English Code
```php
/**
 * Verarbeitet unbekannte Benutzer aus der CSV-Datei und erstellt neue Benutzerkonten
 * 
 * @param array $emailMappings Zuordnung von Benutzername zu E-Mail-Adresse
 * @return UnknownUsersResult Ergebnis der Benutzer-Erstellung
 */
public function processUnknownUsers(array $emailMappings): UnknownUsersResult
```

### Service Constructor Injection
All services use readonly constructor injection:
```php
public function __construct(
    private readonly CsvProcessor $csvProcessor,
    private readonly SessionManager $sessionManager,
    private readonly EmailService $emailService
) {}
```

### Exception Handling Pattern
```php
try {
    $result = $this->service->processData($data);
    $this->addFlash($result->flashType, $result->flashMessage);
    return $this->redirectToRoute($result->redirectRoute, $result->routeParameters);
} catch (TicketMailerException $e) {
    $this->addFlash('error', $e->getUserMessage());
}
```

## Environment & Debugging

### Container Services
- **PHP**: `ticketumfrage_php` (main application)
- **Database**: `ticketumfrage_database` (MariaDB)
- **Web**: `ticketumfrage_webserver` (Nginx)
- **Mail**: `mailhog` (test SMTP server on :8025)

### Xdebug Configuration
Xdebug is configured for VS Code debugging and coverage. Use `XDEBUG_MODE=coverage` for test coverage generation.

### Multi-Platform Support
```bash
# ARM64 (Raspberry Pi)
DOCKER_PLATFORM=linux/arm64v8 docker compose up -d
```

## Business Logic Specifics

### Email Template System
Templates support placeholders: `{{ticketId}}`, `{{username}}`, `{{ticketName}}`, `{{ticketLink}}`
Test mode automatically adds test indicators to subject and body.

### CSV Field Configuration
Dynamic CSV column mapping via `CsvFieldConfig` entity - allows runtime field assignment without code changes.

### Session-Based Workflow
Multi-step workflow stores intermediate data in session:
- Unknown users list for manual email assignment
- Valid tickets for email sending
- Test email address for test mode

### Duplicate Detection
Emails are checked against `emails_sent` table. Use `forceResend=true` to bypass duplicate protection.

## Key Files to Reference
- `src/Service/CsvUploadOrchestrator.php`: Main workflow coordination
- `src/Controller/CsvUploadController.php`: HTTP handling patterns
- `src/Service/SessionManager.php`: Session state management
- `Makefile`: All development commands
- `tests/Controller/CsvUploadControllerTest.php`: Testing patterns

When implementing new features, follow the established Service → Controller → Template pattern and always add corresponding unit tests.
