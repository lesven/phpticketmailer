#!/bin/bash

# ==================================================================
# Master-Testscript für Ticketumfrage-Tool
# ==================================================================
# Führt alle verfügbaren Regressionstests aus
#
# Verwendung: ./run_all_tests.sh [--quick|--full|--database-only]
# ==================================================================

set -e

# Farben für Output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
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

log_header() {
    echo -e "\n${BOLD}${BLUE}$1${NC}"
    echo "=================================================="
}

show_usage() {
    echo "Verwendung: $0 [OPTION]"
    echo ""
    echo "Optionen:"
    echo "  --quick         Nur schnelle Tests (Standard)"
    echo "  --full          Alle Tests inklusive Performance"
    echo "  --database-only Nur Datenbanktests"
    echo "  --help          Diese Hilfe anzeigen"
    echo ""
    echo "Verfügbare Testscripts:"
    echo "  ./test_versandprotokoll.sh  - Userstory 20 Regressionstests"
    echo "  ./test_database.sh          - Erweiterte Datenbanktests"
    echo ""
}

check_test_scripts() {
    log_info "Überprüfe verfügbare Testscripts..."
    
    local scripts=("test_versandprotokoll.sh" "test_database.sh")
    local missing_scripts=()
    
    for script in "${scripts[@]}"; do
        if [ -f "$script" ] && [ -x "$script" ]; then
            log_success "✓ $script"
        else
            log_error "✗ $script (fehlt oder nicht ausführbar)"
            missing_scripts+=("$script")
        fi
    done
    
    if [ ${#missing_scripts[@]} -gt 0 ]; then
        log_error "Fehlende Testscripts gefunden!"
        exit 1
    fi
}

run_quick_tests() {
    log_header "SCHNELLE REGRESSIONSTESTS"
    
    log_info "Führe Versandprotokoll-Tests aus..."
    if ./test_versandprotokoll.sh; then
        log_success "Versandprotokoll-Tests bestanden"
    else
        log_error "Versandprotokoll-Tests fehlgeschlagen!"
        return 1
    fi
    
    log_info "Führe Basis-Datenbanktests aus..."
    if ./test_database.sh; then
        log_success "Datenbanktests bestanden"
    else
        log_error "Datenbanktests fehlgeschlagen!"
        return 1
    fi
}

run_full_tests() {
    log_header "VOLLSTÄNDIGE REGRESSIONSTESTS"
    
    # Führe zuerst schnelle Tests aus
    if ! run_quick_tests; then
        log_error "Basis-Tests fehlgeschlagen, breche ab!"
        return 1
    fi
    
    log_header "ERWEITERTE TESTS"
    
    # Zusätzliche Tests könnten hier hinzugefügt werden
    log_info "Teste Symfony-Konfiguration..."
    if php bin/console about > /dev/null 2>&1; then
        log_success "Symfony-Konfiguration OK"
    else
        log_error "Symfony-Konfiguration fehlerhaft!"
        return 1
    fi
    
    log_info "Teste Composer-Abhängigkeiten..."
    if composer validate --no-check-publish > /dev/null 2>&1; then
        log_success "Composer-Konfiguration valide"
    else
        log_warning "Composer-Konfiguration könnte Probleme haben"
    fi
    
    log_info "Überprüfe Doctrine-Schema..."
    if php bin/console doctrine:schema:validate > /dev/null 2>&1; then
        log_success "Doctrine-Schema valide"
    else
        log_warning "Doctrine-Schema könnte Abweichungen haben"
    fi
}

run_database_only() {
    log_header "NUR DATENBANKTESTS"
    
    if ./test_database.sh; then
        log_success "Alle Datenbanktests bestanden"
    else
        log_error "Datenbanktests fehlgeschlagen!"
        return 1
    fi
}

generate_test_report() {
    local test_type="$1"
    local start_time="$2"
    local end_time="$3"
    local success="$4"
    
    local duration=$(( end_time - start_time ))
    
    log_header "TEST-BERICHT"
    echo "Test-Typ:     $test_type"
    echo "Startzeit:    $(date -r $start_time '+%Y-%m-%d %H:%M:%S')"
    echo "Endzeit:      $(date -r $end_time '+%Y-%m-%d %H:%M:%S')"
    echo "Dauer:        ${duration} Sekunden"
    echo "Status:       $([ "$success" = "true" ] && echo -e "${GREEN}ERFOLGREICH${NC}" || echo -e "${RED}FEHLGESCHLAGEN${NC}")"
    echo ""
    
    if [ "$success" = "true" ]; then
        log_success "Alle Tests erfolgreich abgeschlossen! 🎉"
        echo ""
        echo "Das Ticketumfrage-Tool ist bereit für den Produktionseinsatz."
    else
        log_error "Tests fehlgeschlagen! Bitte Fehler beheben vor Deployment."
        echo ""
        echo "Überprüfen Sie die Ausgabe oben für Details zu den Fehlern."
    fi
}

main() {
    local test_type="quick"
    local start_time=$(date +%s)
    local success="false"
    
    # Parameter verarbeiten
    case "${1:-}" in
        --quick)
            test_type="quick"
            ;;
        --full)
            test_type="full"
            ;;
        --database-only)
            test_type="database"
            ;;
        --help|-h)
            show_usage
            exit 0
            ;;
        "")
            test_type="quick"
            ;;
        *)
            log_error "Unbekannte Option: $1"
            show_usage
            exit 1
            ;;
    esac
    
    log_header "TICKETUMFRAGE-TOOL REGRESSIONSTESTS"
    echo "Test-Modus: $test_type"
    echo "Gestartet: $(date)"
    echo ""
    
    # Überprüfe Testscripts
    check_test_scripts
    
    # Führe Tests basierend auf gewähltem Modus aus
    case "$test_type" in
        quick)
            if run_quick_tests; then
                success="true"
            fi
            ;;
        full)
            if run_full_tests; then
                success="true"
            fi
            ;;
        database)
            if run_database_only; then
                success="true"
            fi
            ;;
    esac
    
    local end_time=$(date +%s)
    generate_test_report "$test_type" "$start_time" "$end_time" "$success"
    
    # Exit-Code basierend auf Erfolg
    [ "$success" = "true" ] && exit 0 || exit 1
}

# Führe main-Funktion aus, wenn Script direkt aufgerufen wird
if [ "${BASH_SOURCE[0]}" = "${0}" ]; then
    main "$@"
fi
