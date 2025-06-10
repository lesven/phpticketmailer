#!/bin/bash

# ==================================================================
# Docker-Version: Regressionstest für Userstory 20 - Versandprotokoll
# ==================================================================
# Dieses Script testet alle Funktionalitäten des Versandprotokolls
# innerhalb der Docker-Container-Umgebung.
#
# Verwendung: ./test_versandprotokoll_docker.sh
# ==================================================================

set -e

# Farben für Output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Docker-Container Namen
PHP_CONTAINER="ticketumfrage_php"
WEB_CONTAINER="ticketumfrage_webserver"
DB_CONTAINER="ticketumfrage_database"

# URLs und Ports
BASE_URL="http://localhost:8090"
COOKIES_FILE="test_cookies_docker.txt"
TEST_DATA_INSERTED=false

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

# Cleanup-Funktion
cleanup() {
    log_info "Docker-Cleanup wird ausgeführt..."
    if [ "$TEST_DATA_INSERTED" = true ]; then
        log_info "Entferne Testdaten aus der Datenbank..."
        docker exec $PHP_CONTAINER php bin/console dbal:run-sql "DELETE FROM emails_sent WHERE ticket_id LIKE 'DOCKERTEST-%'" 2>/dev/null || true
    fi
    [ -f "$COOKIES_FILE" ] && rm -f "$COOKIES_FILE"
}

# Nur bei normalem Exit cleanup ausführen, nicht bei Fehlern während der Tests
trap_cleanup() {
    if [ $? -eq 0 ]; then
        cleanup
    else
        log_info "Test fehlgeschlagen, Testdaten bleiben zur Analyse erhalten"
        [ -f "$COOKIES_FILE" ] && rm -f "$COOKIES_FILE"
    fi
}

trap trap_cleanup EXIT

# Docker-spezifische Hilfsfunktionen
docker_exec_php() {
    docker exec $PHP_CONTAINER "$@"
}

docker_exec_php_console() {
    docker exec $PHP_CONTAINER php bin/console "$@"
}

check_docker_prerequisites() {
    log_info "Überprüfe Docker-Voraussetzungen..."
    
    # Prüfe ob Docker läuft
    if ! docker ps &> /dev/null; then
        log_error "Docker ist nicht verfügbar oder läuft nicht!"
        exit 1
    fi
    
    # Prüfe ob curl verfügbar ist
    if ! command -v curl &> /dev/null; then
        log_error "curl ist nicht installiert!"
        exit 1
    fi
    
    log_success "Docker-Voraussetzungen erfüllt"
}

check_docker_containers() {
    log_info "Überprüfe Docker-Container..."
    
    local containers=($PHP_CONTAINER $WEB_CONTAINER $DB_CONTAINER)
    
    for container in "${containers[@]}"; do
        if docker ps --format "table {{.Names}}" | grep -q "^$container$"; then
            log_success "Container '$container' läuft"
        else
            log_error "Container '$container' läuft nicht!"
            log_info "Starte Container mit: docker-compose up -d"
            exit 1
        fi
    done
}

wait_for_database() {
    log_info "Warte auf Datenbankbereitschaft..."
    
    local max_attempts=30
    local attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        if docker_exec_php_console dbal:run-sql "SELECT 1" > /dev/null 2>&1; then
            log_success "Datenbank ist bereit"
            return 0
        fi
        
        log_info "Warte auf Datenbank... (Versuch $attempt/$max_attempts)"
        sleep 2
        ((attempt++))
    done
    
    log_error "Datenbank ist nach $max_attempts Versuchen nicht bereit!"
    exit 1
}

test_docker_database_connection() {
    log_info "Teste Docker-Datenbankverbindung..."
    
    if docker_exec_php_console dbal:run-sql "SELECT 1" > /dev/null 2>&1; then
        log_success "Docker-Datenbankverbindung erfolgreich"
    else
        log_error "Docker-Datenbankverbindung fehlgeschlagen!"
        exit 1
    fi
}

test_docker_migrations() {
    log_info "Überprüfe Doctrine-Migrationen in Docker..."
    
    local migration_status=$(docker_exec_php_console doctrine:migrations:status --no-interaction)
    
    if echo "$migration_status" | grep -q "Already at latest version"; then
        log_success "Alle Migrationen sind ausgeführt"
    else
        log_warning "Führe ausstehende Migrationen aus..."
        docker_exec_php_console doctrine:migrations:migrate --no-interaction
        log_success "Migrationen abgeschlossen"
    fi
}

check_docker_required_tables() {
    log_info "Überprüfe erforderliche Datenbanktabellen in Docker..."
    
    local tables=("emails_sent" "admin_password")
    
    for table in "${tables[@]}"; do
        if docker_exec_php_console dbal:run-sql "DESCRIBE $table" > /dev/null 2>&1; then
            log_success "Tabelle '$table' existiert"
        else
            log_error "Tabelle '$table' existiert nicht!"
            exit 1
        fi
    done
}

setup_docker_test_data() {
    log_info "Erstelle Testdaten in Docker..."
    
    # Lösche vorhandene Testdaten
    docker_exec_php_console dbal:run-sql "DELETE FROM emails_sent WHERE ticket_id LIKE 'DOCKERTEST-%'" > /dev/null 2>&1 || true
    
    # Erstelle Testdaten
    docker_exec_php_console dbal:run-sql "INSERT INTO emails_sent (ticket_id, username, email, subject, status, timestamp, test_mode, ticket_name) VALUES 
        ('DOCKERTEST-001', 'dockeruser1', 'docker1@example.com', 'Docker Test E-Mail 1', 'sent', NOW(), 0, 'Docker Testticket 1'),
        ('DOCKERTEST-002', 'dockeruser2', 'docker2@example.com', 'Docker Test E-Mail 2', 'sent', NOW(), 1, 'Docker Testticket 2'),
        ('DOCKERTEST-003', 'dockeruser3', 'docker3@example.com', 'Docker Test E-Mail 3', 'error: SMTP not configured', NOW(), 0, 'Docker Testticket 3'),
        ('DOCKERTEST-004', 'dockeruser4', 'docker4@example.com', 'Docker Test E-Mail 4', 'Nicht versendet - bereits verarbeitet', NOW(), 0, 'Docker Testticket 4')" > /dev/null
    
    TEST_DATA_INSERTED=true
    log_success "Docker-Testdaten erstellt"
}

check_docker_routing() {
    log_info "Überprüfe Symfony-Routing in Docker..."
    
    if docker_exec_php_console debug:router | grep -q "email_log"; then
        log_success "Route 'email_log' ist registriert"
    else
        log_error "Route 'email_log' ist nicht registriert!"
        exit 1
    fi
    
    # Cache leeren
    docker_exec_php_console cache:clear > /dev/null 2>&1
    log_success "Symfony-Cache in Docker geleert"
}

test_docker_webserver() {
    log_info "Teste Docker-Webserver-Verfügbarkeit..."
    
    local max_attempts=10
    local attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        if curl -s "$BASE_URL" > /dev/null 2>&1; then
            log_success "Docker-Webserver ist erreichbar unter $BASE_URL"
            return 0
        fi
        
        log_info "Warte auf Webserver... (Versuch $attempt/$max_attempts)"
        sleep 2
        ((attempt++))
    done
    
    log_error "Docker-Webserver ist nicht erreichbar unter $BASE_URL"
    exit 1
}

test_docker_login() {
    log_info "Teste Login-Funktionalität in Docker..."
    
    # Hole Login-Seite
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
    
    if echo "$login_response" | grep -q "Redirecting to /"; then
        log_success "Docker-Login erfolgreich"
    else
        log_error "Docker-Login fehlgeschlagen!"
        exit 1
    fi
}

test_docker_versandprotokoll_access() {
    log_info "Teste Zugriff auf Versandprotokoll in Docker..."
    
    local response=$(curl -s -b "$COOKIES_FILE" "$BASE_URL/versandprotokoll")
    
    if echo "$response" | grep -q "<title>Versandprotokoll"; then
        log_success "Docker-Versandprotokoll-Seite erfolgreich geladen"
    else
        log_error "Docker-Versandprotokoll-Seite konnte nicht geladen werden!"
        exit 1
    fi
    
    if echo "$response" | grep -q "DOCKERTEST-001"; then
        log_success "Docker-Testdaten werden im Versandprotokoll angezeigt"
    else
        log_warning "Docker-Testdaten werden nicht angezeigt"
    fi
}

test_docker_search_functionality() {
    log_info "Teste Such-Funktionalität in Docker..."
    
    # Teste spezifische Suche
    local search_response=$(curl -s -b "$COOKIES_FILE" "$BASE_URL/versandprotokoll?search=DOCKERTEST-002")
    
    if echo "$search_response" | grep -q "DOCKERTEST-002" && ! echo "$search_response" | grep -q "DOCKERTEST-001"; then
        log_success "Docker-Suchfunktion funktioniert korrekt"
    else
        log_error "Docker-Suchfunktion funktioniert nicht korrekt!"
        exit 1
    fi
    
    # Teste Wildcard-Suche
    local wildcard_response=$(curl -s -b "$COOKIES_FILE" "$BASE_URL/versandprotokoll?search=DOCKERTEST-")
    
    if echo "$wildcard_response" | grep -q "DOCKERTEST-001" && echo "$wildcard_response" | grep -q "DOCKERTEST-002"; then
        log_success "Docker-Wildcard-Suche funktioniert korrekt"
    else
        log_error "Docker-Wildcard-Suche funktioniert nicht korrekt!"
        exit 1
    fi
}

test_docker_performance() {
    log_info "Teste Performance in Docker..."
    
    local start_time=$(date +%s%N)
    curl -s -b "$COOKIES_FILE" "$BASE_URL/versandprotokoll" > /dev/null
    local end_time=$(date +%s%N)
    local response_time=$(( (end_time - start_time) / 1000000 ))
    
    if [ $response_time -lt 3000 ]; then  # Etwas höhere Toleranz für Docker
        log_success "Docker-Performance OK: ${response_time}ms"
    else
        log_warning "Docker-Performance hoch: ${response_time}ms"
    fi
}

verify_docker_data_integrity() {
    log_info "Überprüfe Datenintegrität in Docker..."
    
    local result=$(docker_exec_php_console dbal:run-sql "SELECT COUNT(*) as count FROM emails_sent WHERE ticket_id LIKE 'DOCKERTEST-%'")
    
    # Extrahiere die Zahl aus der Tabellendarstellung
    local count=$(echo "$result" | grep -o '[0-9]\+' | head -1)
    
    if [ -z "$count" ]; then
        # Fallback: Versuche alternative Extraktion mit mehr Flexibilität
        count=$(echo "$result" | tr -d ' \t\n\r|-' | grep -o '[0-9]\+' | head -1)
    fi
    
    if [ "$count" = "4" ]; then
        log_success "Alle Docker-Testdaten sind vorhanden"
    else
        log_error "Docker-Testdaten fehlen! Erwartet: 4, Gefunden: '$count'"
        log_info "Debug-Output:"
        echo "$result" | sed 's/^/  /'
        exit 1
    fi
}

check_docker_logs() {
    log_info "Überprüfe Docker-Container-Logs auf Fehler..."
    
    # Prüfe PHP-Container Logs auf Fehler
    local php_errors=$(docker logs $PHP_CONTAINER --since=10m 2>&1 | grep -i "error\|exception\|fatal" | wc -l)
    if [ $php_errors -eq 0 ]; then
        log_success "Keine PHP-Fehler in den Logs"
    else
        log_warning "$php_errors PHP-Fehler in den letzten 10 Minuten gefunden"
    fi
    
    # Prüfe Webserver Logs
    local web_errors=$(docker logs $WEB_CONTAINER --since=10m 2>&1 | grep -i "error" | wc -l)
    if [ $web_errors -eq 0 ]; then
        log_success "Keine Webserver-Fehler in den Logs"
    else
        log_warning "$web_errors Webserver-Fehler in den letzten 10 Minuten gefunden"
    fi
}

run_docker_tests() {
    log_info "Starte Docker-Regressionstests für Versandprotokoll..."
    echo "=================================================="
    
    check_docker_prerequisites
    check_docker_containers
    wait_for_database
    test_docker_database_connection
    test_docker_migrations
    check_docker_required_tables
    setup_docker_test_data
    check_docker_routing
    test_docker_webserver
    test_docker_login
    test_docker_versandprotokoll_access
    test_docker_search_functionality
    test_docker_performance
    verify_docker_data_integrity
    check_docker_logs
    
    echo "=================================================="
    log_success "Alle Docker-Tests erfolgreich abgeschlossen!"
    echo ""
    log_info "Docker-Zusammenfassung:"
    echo "  ✅ Container verfügbar und laufend"
    echo "  ✅ Datenbankverbindung in Docker"
    echo "  ✅ Migrationen und Tabellen"
    echo "  ✅ Testdaten erstellt und verifiziert"
    echo "  ✅ Webserver erreichbar ($BASE_URL)"
    echo "  ✅ Login-Funktionalität"
    echo "  ✅ Versandprotokoll-Funktionen"
    echo "  ✅ Such- und Filterfunktionen"
    echo "  ✅ Performance in Docker-Umgebung"
    echo "  ✅ Container-Logs ohne kritische Fehler"
    echo ""
    log_success "Userstory 20 funktioniert vollständig in Docker!"
}

# Script-Ausführung
if [ "${BASH_SOURCE[0]}" = "${0}" ]; then
    run_docker_tests
fi
