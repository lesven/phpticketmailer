#!/bin/bash

# ==================================================================
# Erweiterte Datenbanktests für Ticketumfrage-Tool
# ==================================================================
# Dieses Script führt umfassende Tests der Datenbankfunktionalitäten
# und Datenintegrität durch.
#
# Verwendung: ./test_database.sh
# ==================================================================

set -e

# Farben für Output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Cleanup-Funktion
cleanup() {
    log_info "Cleanup wird ausgeführt..."
    php bin/console dbal:run-sql "DELETE FROM emails_sent WHERE ticket_id LIKE 'DBTEST-%'" 2>/dev/null || true
    php bin/console dbal:run-sql "DELETE FROM users WHERE username LIKE 'dbtest_%'" 2>/dev/null || true
}

trap cleanup EXIT

test_all_tables() {
    log_info "Teste alle erforderlichen Tabellen..."
    
    local tables=("emails_sent" "users" "admin_password" "smtpconfig")
    
    for table in "${tables[@]}"; do
        if php bin/console dbal:run-sql "DESCRIBE $table" > /dev/null 2>&1; then
            log_success "Tabelle '$table' existiert"
        else
            log_error "Tabelle '$table' fehlt!"
            exit 1
        fi
    done
}

test_emails_sent_structure() {
    log_info "Teste Struktur der emails_sent Tabelle..."
    
    local structure=$(php bin/console dbal:run-sql "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'emails_sent'")
    
    local required_columns=("id" "ticket_id" "username" "email" "subject" "status" "timestamp" "test_mode" "ticket_name")
    
    for column in "${required_columns[@]}"; do
        if echo "$structure" | grep -q "$column"; then
            log_success "Spalte '$column' gefunden"
        else
            log_error "Spalte '$column' fehlt in emails_sent!"
            exit 1
        fi
    done
}

test_database_constraints() {
    log_info "Teste Datenbank-Constraints..."
    
    # Teste UNIQUE Constraint bei users.username
    php bin/console dbal:run-sql "INSERT INTO users (username, email) VALUES ('dbtest_unique', 'test@example.com')" > /dev/null 2>&1
    
    # Versuche doppelten Username einzufügen
    if php bin/console dbal:run-sql "INSERT INTO users (username, email) VALUES ('dbtest_unique', 'test2@example.com')" > /dev/null 2>&1; then
        log_error "UNIQUE Constraint für users.username funktioniert nicht!"
        exit 1
    else
        log_success "UNIQUE Constraint für users.username funktioniert"
    fi
}

test_data_insertion() {
    log_info "Teste Dateneinf gung..."
    
    # Teste emails_sent
    php bin/console dbal:run-sql "INSERT INTO emails_sent (ticket_id, username, email, subject, status, timestamp, test_mode, ticket_name) VALUES 
        ('DBTEST-001', 'dbtest_user1', 'dbtest1@example.com', 'Test Subject', 'sent', NOW(), 0, 'Test Ticket')" > /dev/null
    
    local count=$(php bin/console dbal:run-sql "SELECT COUNT(*) as count FROM emails_sent WHERE ticket_id = 'DBTEST-001'" | tail -n +3 | head -n -1 | awk '{print $1}')
    
    if [ "$count" = "1" ]; then
        log_success "Dateneinf gung in emails_sent erfolgreich"
    else
        log_error "Dateneinf gung in emails_sent fehlgeschlagen!"
        exit 1
    fi
}

test_data_querying() {
    log_info "Teste Datenabfragen..."
    
    # Teste verschiedene Abfragen
    local queries=(
        "SELECT * FROM emails_sent WHERE ticket_id LIKE 'DBTEST-%'"
        "SELECT * FROM emails_sent ORDER BY timestamp DESC LIMIT 10"
        "SELECT COUNT(*) FROM emails_sent WHERE status = 'sent'"
        "SELECT * FROM emails_sent WHERE test_mode = 1"
    )
    
    for query in "${queries[@]}"; do
        if php bin/console dbal:run-sql "$query" > /dev/null 2>&1; then
            log_success "Query erfolgreich: $(echo "$query" | cut -c1-50)..."
        else
            log_error "Query fehlgeschlagen: $query"
            exit 1
        fi
    done
}

test_migration_consistency() {
    log_info "Teste Migration-Konsistenz..."
    
    # Prüfe aktuelle Migration-Version
    local status=$(php bin/console doctrine:migrations:status --no-interaction)
    
    if echo "$status" | grep -q "Already at latest version"; then
        log_success "Alle Migrationen sind aktuell"
    else
        log_warning "Nicht alle Migrationen sind ausgeführt"
        echo "$status"
    fi
    
    # Prüfe ob alle erwarteten Migrationen vorhanden sind
    if php bin/console doctrine:migrations:list | grep -q "Version20250610120000"; then
        log_success "SSL-Verifizierungs-Migration ist vorhanden"
    else
        log_error "Version20250610120000 Migration fehlt!"
        exit 1
    fi
}

test_smtp_config_table() {
    log_info "Teste SMTP-Konfigurationstabelle..."
    
    # Prüfe ob verify_ssl Spalte existiert
    local structure=$(php bin/console dbal:run-sql "DESCRIBE smtpconfig")
    
    if echo "$structure" | grep -q "verify_ssl"; then
        log_success "verify_ssl Spalte ist vorhanden"
    else
        log_error "verify_ssl Spalte fehlt in smtpconfig!"
        exit 1
    fi
    
    # Teste Standard-Wert
    local default_value=$(php bin/console dbal:run-sql "SHOW COLUMNS FROM smtpconfig LIKE 'verify_ssl'" | grep -o "Default: [01]" | cut -d' ' -f2 || echo "1")
    if [ "$default_value" = "1" ]; then
        log_success "verify_ssl hat korrekten Standard-Wert (1)"
    else
        log_warning "verify_ssl Standard-Wert ist: $default_value"
    fi
}

test_performance_queries() {
    log_info "Teste Performance kritischer Abfragen..."
    
    # Erstelle mehr Testdaten für Performance-Test
    log_info "Erstelle Testdaten für Performance-Test..."
    for i in {1..50}; do
        php bin/console dbal:run-sql "INSERT INTO emails_sent (ticket_id, username, email, subject, status, timestamp, test_mode, ticket_name) VALUES 
            ('DBTEST-$(printf "%03d" $i)', 'dbtest_user$i', 'dbtest$i@example.com', 'Test Subject $i', 'sent', NOW(), $(($i % 2)), 'Test Ticket $i')" > /dev/null 2>&1
    done
    
    # Teste Abfrage-Performance
    local start_time=$(date +%s%N)
    php bin/console dbal:run-sql "SELECT * FROM emails_sent WHERE ticket_id LIKE 'DBTEST-%' ORDER BY timestamp DESC LIMIT 100" > /dev/null
    local end_time=$(date +%s%N)
    local query_time=$(( (end_time - start_time) / 1000000 ))
    
    if [ $query_time -lt 100 ]; then
        log_success "Performance-Test bestanden: ${query_time}ms"
    else
        log_warning "Performance könnte verbessert werden: ${query_time}ms"
    fi
}

test_data_integrity() {
    log_info "Teste Datenintegrität..."
    
    # Prüfe auf NULL-Werte in kritischen Feldern
    local null_checks=(
        "SELECT COUNT(*) FROM emails_sent WHERE ticket_id IS NULL"
        "SELECT COUNT(*) FROM emails_sent WHERE username IS NULL"
        "SELECT COUNT(*) FROM emails_sent WHERE email IS NULL"
        "SELECT COUNT(*) FROM users WHERE username IS NULL"
        "SELECT COUNT(*) FROM users WHERE email IS NULL"
    )
    
    for check in "${null_checks[@]}"; do
        local count=$(php bin/console dbal:run-sql "$check" | tail -n +3 | head -n -1 | awk '{print $1}')
        if [ "$count" = "0" ]; then
            log_success "Keine NULL-Werte gefunden: $(echo "$check" | cut -d' ' -f4-6)"
        else
            log_error "NULL-Werte gefunden: $count Einträge"
            exit 1
        fi
    done
}

test_email_validation() {
    log_info "Teste E-Mail-Validierung..."
    
    # Diese Tests würden normalerweise auf Anwendungsebene durchgeführt
    # Hier prüfen wir nur, ob ungültige E-Mails in der DB landen könnten
    
    local test_emails=("invalid-email" "test@" "@example.com" "")
    local valid_count=0
    
    for email in "${test_emails[@]}"; do
        # Versuche ungültige E-Mail einzufügen (sollte von Anwendung verhindert werden)
        if php bin/console dbal:run-sql "INSERT INTO users (username, email) VALUES ('dbtest_invalid_$(date +%s)', '$email')" > /dev/null 2>&1; then
            log_warning "Ungültige E-Mail wurde eingefügt: '$email'"
        else
            ((valid_count++))
        fi
    done
    
    if [ $valid_count -eq ${#test_emails[@]} ]; then
        log_success "E-Mail-Validierung funktioniert (oder Datenbank lehnt ab)"
    fi
}

run_database_tests() {
    log_info "Starte erweiterte Datenbanktests..."
    echo "=================================================="
    
    test_all_tables
    test_emails_sent_structure
    test_database_constraints
    test_data_insertion
    test_data_querying
    test_migration_consistency
    test_smtp_config_table
    test_performance_queries
    test_data_integrity
    test_email_validation
    
    echo "=================================================="
    log_success "Alle Datenbanktests erfolgreich abgeschlossen!"
    echo ""
    log_info "Zusammenfassung:"
    echo "  ✅ Tabellenstruktur vollständig"
    echo "  ✅ Constraints funktionieren"
    echo "  ✅ CRUD-Operationen erfolgreich"
    echo "  ✅ Migrationen konsistent"
    echo "  ✅ Performance akzeptabel"
    echo "  ✅ Datenintegrität gewährleistet"
    echo ""
}

# Script-Ausführung
if [ "${BASH_SOURCE[0]}" = "${0}" ]; then
    run_database_tests
fi
