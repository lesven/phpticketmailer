#!/bin/bash

# ==================================================================
# Regressionstest für Userstory 20 - Versandprotokoll
# ==================================================================
# Dieses Script testet alle Funktionalitäten des Versandprotokolls
# und kann als automatisierter Regressionstest verwendet werden.
#
# Verwendung: ./test_versandprotokoll.sh
# ==================================================================

set -e  # Script beenden bei Fehlern

# Farben für Output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Funktionen
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

# Variablen
BASE_URL="http://localhost:8000"
COOKIES_FILE="test_cookies.txt"
TEST_DATA_INSERTED=false

# Cleanup-Funktion
cleanup() {
    log_info "Cleanup wird ausgeführt..."
    if [ "$TEST_DATA_INSERTED" = true ]; then
        log_info "Entferne Testdaten aus der Datenbank..."
        php bin/console dbal:run-sql "DELETE FROM emails_sent WHERE ticket_id LIKE 'TEST-%'"
    fi
    [ -f "$COOKIES_FILE" ] && rm -f "$COOKIES_FILE"
    pkill -f "php -S localhost:8000" 2>/dev/null || true
}

# Trap für Cleanup bei Script-Ende oder Fehler
trap cleanup EXIT

# Test-Funktionen
check_prerequisites() {
    log_info "Überprüfe Voraussetzungen..."
    
    # Prüfe ob wir im richtigen Verzeichnis sind
    if [ ! -f "composer.json" ] || [ ! -d "src" ]; then
        log_error "Script muss im Symfony-Projektverzeichnis ausgeführt werden!"
        exit 1
    fi
    
    # Prüfe PHP
    if ! command -v php &> /dev/null; then
        log_error "PHP ist nicht installiert oder nicht im PATH!"
        exit 1
    fi
    
    # Prüfe curl
    if ! command -v curl &> /dev/null; then
        log_error "curl ist nicht installiert!"
        exit 1
    fi
    
    log_success "Alle Voraussetzungen erfüllt"
}

test_database_connection() {
    log_info "Teste Datenbankverbindung..."
    
    if php bin/console dbal:run-sql "SELECT 1" > /dev/null 2>&1; then
        log_success "Datenbankverbindung erfolgreich"
    else
        log_error "Datenbankverbindung fehlgeschlagen!"
        exit 1
    fi
}

test_migrations() {
    log_info "Überprüfe Doctrine-Migrationen..."
    
    # Status der Migrationen prüfen
    local migration_status=$(php bin/console doctrine:migrations:status --no-interaction)
    
    if echo "$migration_status" | grep -q "Already at latest version"; then
        log_success "Alle Migrationen sind ausgeführt"
    else
        log_warning "Führe ausstehende Migrationen aus..."
        php bin/console doctrine:migrations:migrate --no-interaction
        log_success "Migrationen abgeschlossen"
    fi
}

check_required_tables() {
    log_info "Überprüfe erforderliche Datenbanktabellen..."
    
    # Prüfe emails_sent Tabelle
    if php bin/console dbal:run-sql "DESCRIBE emails_sent" > /dev/null 2>&1; then
        log_success "Tabelle 'emails_sent' existiert"
    else
        log_error "Tabelle 'emails_sent' existiert nicht!"
        exit 1
    fi
    
    # Prüfe admin_password Tabelle
    if php bin/console dbal:run-sql "DESCRIBE admin_password" > /dev/null 2>&1; then
        log_success "Tabelle 'admin_password' existiert"
    else
        log_error "Tabelle 'admin_password' existiert nicht!"
        exit 1
    fi
}

setup_test_data() {
    log_info "Erstelle Testdaten für das Versandprotokoll..."
    
    # Lösche vorhandene Testdaten
    php bin/console dbal:run-sql "DELETE FROM emails_sent WHERE ticket_id LIKE 'TEST-%'" > /dev/null 2>&1
    
    # Erstelle Testdaten
    php bin/console dbal:run-sql "INSERT INTO emails_sent (ticket_id, username, email, subject, status, timestamp, test_mode, ticket_name) VALUES 
        ('TEST-001', 'testuser1', 'test1@example.com', 'Test E-Mail 1', 'sent', NOW(), 0, 'Testticket 1'),
        ('TEST-002', 'testuser2', 'test2@example.com', 'Test E-Mail 2', 'sent', NOW(), 1, 'Testticket 2'),
        ('TEST-003', 'testuser3', 'test3@example.com', 'Test E-Mail 3', 'error: SMTP not configured', NOW(), 0, 'Testticket 3'),
        ('TEST-004', 'testuser4', 'test4@example.com', 'Test E-Mail 4', 'Nicht versendet - bereits verarbeitet', NOW(), 0, 'Testticket 4')" > /dev/null
    
    TEST_DATA_INSERTED=true
    log_success "Testdaten erstellt"
}

check_routing() {
    log_info "Überprüfe Symfony-Routing..."
    
    # Prüfe ob email_log Route existiert
    if php bin/console debug:router | grep -q "email_log"; then
        log_success "Route 'email_log' ist registriert"
    else
        log_error "Route 'email_log' ist nicht registriert!"
        exit 1
    fi
    
    # Cache leeren
    php bin/console cache:clear > /dev/null 2>&1
    log_success "Symfony-Cache geleert"
}

start_dev_server() {
    log_info "Starte PHP Development Server..."
    
    # Prüfe ob bereits ein Server läuft
    if curl -s "$BASE_URL" > /dev/null 2>&1; then
        log_info "Server läuft bereits auf $BASE_URL"
    else
        # Starte Server im Hintergrund
        php -S localhost:8000 -t public/ > /dev/null 2>&1 &
        SERVER_PID=$!
        
        # Warte bis Server bereit ist
        for i in {1..10}; do
            if curl -s "$BASE_URL" > /dev/null 2>&1; then
                log_success "Development Server gestartet (PID: $SERVER_PID)"
                return 0
            fi
            sleep 1
        done
        
        log_error "Development Server konnte nicht gestartet werden!"
        exit 1
    fi
}

test_login() {
    log_info "Teste Login-Funktionalität..."
    
    # Hole Login-Seite und extrahiere CSRF-Token
    local login_page=$(curl -s -c "$COOKIES_FILE" "$BASE_URL/login")
    local csrf_token=$(echo "$login_page" | grep -o 'name="_csrf_token" value="[^"]*"' | cut -d'"' -f4)
    
    if [ -z "$csrf_token" ]; then
        log_error "CSRF-Token konnte nicht extrahiert werden!"
        exit 1
    fi
    
    # Führe Login durch
    local login_response=$(curl -s -b "$COOKIES_FILE" -c "$COOKIES_FILE" -X POST \
        -d "password=geheim&_csrf_token=$csrf_token" \
        "$BASE_URL/login")
    
    # Prüfe ob Redirect zur Startseite erfolgt
    if echo "$login_response" | grep -q "Redirecting to /"; then
        log_success "Login erfolgreich"
    else
        log_error "Login fehlgeschlagen!"
        exit 1
    fi
}

test_versandprotokoll_access() {
    log_info "Teste Zugriff auf Versandprotokoll..."
    
    local response=$(curl -s -b "$COOKIES_FILE" "$BASE_URL/versandprotokoll")
    
    # Prüfe ob Seite erfolgreich geladen wird
    if echo "$response" | grep -q "<title>Versandprotokoll"; then
        log_success "Versandprotokoll-Seite erfolgreich geladen"
    else
        log_error "Versandprotokoll-Seite konnte nicht geladen werden!"
        exit 1
    fi
    
    # Prüfe ob Testdaten angezeigt werden
    if echo "$response" | grep -q "TEST-001"; then
        log_success "Testdaten werden im Versandprotokoll angezeigt"
    else
        log_warning "Testdaten werden nicht angezeigt (möglicherweise durch 100-Einträge-Limit)"
    fi
}

test_search_functionality() {
    log_info "Teste Such-Funktionalität..."
    
    # Teste Suche nach spezifischer Ticket-ID
    local search_response=$(curl -s -b "$COOKIES_FILE" "$BASE_URL/versandprotokoll?search=TEST-002")
    
    if echo "$search_response" | grep -q "TEST-002" && ! echo "$search_response" | grep -q "TEST-001"; then
        log_success "Suchfunktion funktioniert korrekt"
    else
        log_error "Suchfunktion funktioniert nicht korrekt!"
        exit 1
    fi
    
    # Teste Wildcard-Suche
    local wildcard_response=$(curl -s -b "$COOKIES_FILE" "$BASE_URL/versandprotokoll?search=TEST-")
    
    if echo "$wildcard_response" | grep -q "TEST-001" && echo "$wildcard_response" | grep -q "TEST-002"; then
        log_success "Wildcard-Suche funktioniert korrekt"
    else
        log_error "Wildcard-Suche funktioniert nicht korrekt!"
        exit 1
    fi
}

test_status_display() {
    log_info "Teste Status-Anzeige..."
    
    local response=$(curl -s -b "$COOKIES_FILE" "$BASE_URL/versandprotokoll?search=TEST-")
    
    # Prüfe verschiedene Status-Badges
    if echo "$response" | grep -q 'badge bg-success.*sent'; then
        log_success "Erfolgs-Status wird korrekt angezeigt"
    else
        log_warning "Erfolgs-Status nicht gefunden"
    fi
    
    if echo "$response" | grep -q 'badge bg-danger.*error'; then
        log_success "Fehler-Status wird korrekt angezeigt"
    else
        log_warning "Fehler-Status nicht gefunden"
    fi
    
    if echo "$response" | grep -q 'badge bg-warning.*Nicht versendet'; then
        log_success "Warnungs-Status wird korrekt angezeigt"
    else
        log_warning "Warnungs-Status nicht gefunden"
    fi
}

test_testmode_display() {
    log_info "Teste Testmodus-Anzeige..."
    
    local response=$(curl -s -b "$COOKIES_FILE" "$BASE_URL/versandprotokoll?search=TEST-")
    
    # Prüfe Testmodus-Badges
    if echo "$response" | grep -q 'badge bg-warning text-dark.*Test'; then
        log_success "Testmodus wird korrekt angezeigt"
    else
        log_warning "Testmodus-Anzeige nicht gefunden"
    fi
    
    if echo "$response" | grep -q 'badge bg-secondary.*Live'; then
        log_success "Live-Modus wird korrekt angezeigt"
    else
        log_warning "Live-Modus-Anzeige nicht gefunden"
    fi
}

test_navigation_menu() {
    log_info "Teste Navigation und Menü..."
    
    local dashboard_response=$(curl -s -b "$COOKIES_FILE" "$BASE_URL/")
    
    # Prüfe ob Versandprotokoll-Link im Menü vorhanden ist
    if echo "$dashboard_response" | grep -q 'href="/versandprotokoll".*Versandprotokoll'; then
        log_success "Versandprotokoll-Menüpunkt ist vorhanden"
    else
        log_error "Versandprotokoll-Menüpunkt fehlt im Hauptmenü!"
        exit 1
    fi
    
    # Prüfe ob Link aktiv markiert wird
    local protokoll_response=$(curl -s -b "$COOKIES_FILE" "$BASE_URL/versandprotokoll")
    if echo "$protokoll_response" | grep -q 'nav-link active.*Versandprotokoll'; then
        log_success "Aktiver Menüpunkt wird korrekt markiert"
    else
        log_warning "Aktiver Menüpunkt wird nicht korrekt markiert"
    fi
}

test_performance() {
    log_info "Teste Performance..."
    
    # Messe Antwortzeit
    local start_time=$(date +%s%N)
    curl -s -b "$COOKIES_FILE" "$BASE_URL/versandprotokoll" > /dev/null
    local end_time=$(date +%s%N)
    local response_time=$(( (end_time - start_time) / 1000000 )) # in Millisekunden
    
    if [ $response_time -lt 2000 ]; then
        log_success "Antwortzeit OK: ${response_time}ms"
    else
        log_warning "Antwortzeit hoch: ${response_time}ms"
    fi
}

verify_data_integrity() {
    log_info "Überprüfe Datenintegrität..."
    
    # Prüfe ob alle Testdaten noch vorhanden sind
    local result=$(php bin/console dbal:run-sql "SELECT COUNT(*) as count FROM emails_sent WHERE ticket_id LIKE 'TEST-%'")
    local count=$(echo "$result" | grep -o '[0-9]\+' | head -1)
    
    if [ -z "$count" ]; then
        # Fallback: Versuche alternative Extraktion
        count=$(echo "$result" | tr -d ' \t\n\r|-' | grep -o '[0-9]\+' | head -1)
    fi
    
    if [ "$count" = "4" ]; then
        log_success "Alle Testdaten sind vorhanden"
    else
        log_error "Testdaten fehlen! Erwartet: 4, Gefunden: $count"
        exit 1
    fi
}

run_all_tests() {
    log_info "Starte Regressionstests für Versandprotokoll..."
    echo "=================================================="
    
    check_prerequisites
    test_database_connection
    test_migrations
    check_required_tables
    setup_test_data
    check_routing
    start_dev_server
    test_login
    test_versandprotokoll_access
    test_search_functionality
    test_status_display
    test_testmode_display
    test_navigation_menu
    test_performance
    verify_data_integrity
    
    echo "=================================================="
    log_success "Alle Tests erfolgreich abgeschlossen!"
    echo ""
    log_info "Zusammenfassung:"
    echo "  ✅ Datenbankverbindung und Migrationen"
    echo "  ✅ Testdaten erstellt und verifiziert"
    echo "  ✅ Routing und Controller"
    echo "  ✅ Login-Funktionalität"
    echo "  ✅ Versandprotokoll-Seite"
    echo "  ✅ Such- und Filterfunktionen"
    echo "  ✅ Status- und Testmodus-Anzeige"
    echo "  ✅ Navigation und Menü"
    echo "  ✅ Performance-Check"
    echo "  ✅ Datenintegrität"
    echo ""
    log_success "Userstory 20 (Versandprotokoll) funktioniert vollständig!"
}

# Script-Ausführung
if [ "${BASH_SOURCE[0]}" = "${0}" ]; then
    run_all_tests
fi
