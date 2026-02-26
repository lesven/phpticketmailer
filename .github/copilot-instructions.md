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
# GitHub Copilot Instructions — Ticketumfrage-Tool

Purpose: Quick, actionable guidance for AI coding agents working in this Symfony-based CSV → email survey project.

1) Big picture
- Symfony app (Service → Controller → Template separation). CSV files are uploaded and normalized, unknown users get mapped/created, and emails are sent via `EmailService` with duplicate detection.

2) Key components & where to look
- CSV upload orchestration: `src/Service/CsvUploadOrchestrator.php`
- HTTP endpoints: `src/Controller/CsvUploadController.php`
- Session + multi-step forms: `src/Service/SessionManager.php`
- Email send & dedupe: `src/Service/EmailService.php`
- Email normalization: `src/Service/EmailNormalizer.php`
- CSV column config entity: `src/Entity/CsvFieldConfig.php`

3) Important repo conventions (do these exactly)
- Form fields: usernames with dots are converted to underscores for HTML attributes — use `convertUsernameForHtmlAttribute()` helper.
- Services use readonly constructor injection (PHP 8.1+ style). Match signatures and DI config in `config/services.yaml`.
- Exceptions: controllers catch `TicketMailerException` and surface `getUserMessage()` via flash messages.

4) Developer workflow & common commands
- Docker-first: prefer `docker compose up -d` (or `make up`) and run composer/migrations inside `ticketumfrage_php` container.
- Useful Make targets: `make up`, `make composer-install`, `make migrate`, `make exec-php`, `make test`, `make coverage`.
- Run unit/integration tests: `make test`. Run E2E: `./vendor/bin/phpunit --configuration phpunit-e2e.xml` (requires DB + SMTP).

5) Testing notes
- Unit tests live under `tests/Service/` and controller integration tests under `tests/Controller/`. E2E tests are in `tests/E2E/` and require running MailHog (:8025) and DB migrations.
- When adding tests, run them inside the PHP container to ensure extensions (Xdebug) and env are available.

6) Runtime / debugging
- Container names: `ticketumfrage_php`, `ticketumfrage_database`, `ticketumfrage_webserver`, `mailhog`.
- Use `XDEBUG_MODE=coverage` for coverage runs. VS Code debugging configured in project; prefer containerized debug sessions.

7) CSV & email specifics
- CSV mapping is dynamic via `CsvFieldConfig` — changing CSV-to-field mapping is done at runtime, not by editing code.
- Email placeholders: `{{ticketId}}`, `{{ticketName}}`, `{{username}}`, `{{ticketLink}}`.
- Duplicate check: `emails_sent` table; pass `forceResend=true` to bypass.

8) When changing code
- Keep controllers thin: put business logic into Services. Add unit tests for service behavior and small integration tests for controllers.
- Update migrations for schema changes (migrations/). Use the project's migration naming pattern.

9) Files to inspect when troubleshooting
- [src/Service/CsvUploadOrchestrator.php](src/Service/CsvUploadOrchestrator.php) — workflow
- [src/Controller/CsvUploadController.php](src/Controller/CsvUploadController.php) — form handling
- [src/Service/EmailService.php](src/Service/EmailService.php) — SMTP/template logic
- [Makefile](Makefile) — local dev helpers
- [README.md](README.md) — environment & run instructions

10) Quick tips for AI agents
- Prefer small, focused edits; follow existing constructor-injection and exception patterns.
- When modifying forms, remember the username → HTML attribute conversion helper.
- For CSV behavior, look for `CsvProcessor`, `CsvUploadOrchestrator`, and `CsvFieldConfig` rather than assuming fixed column names.
- E2E tests require containers; do not run them locally without the Docker environment.

If anything here is unclear or you'd like examples for a specific area (tests, DI, or CSV mapping), tell me which section to expand.
    $this->addFlash('error', $e->getUserMessage());
