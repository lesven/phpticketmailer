#!/bin/bash
# Test-Script für Webhook-Deployment
# Dieses Script testet den Webhook-Empfänger manuell

set -e

# Farben für Ausgabe
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Standardwerte
DEFAULT_REPOSITORY="your-user/your-repo"

# Konfiguration
WEBHOOK_URL="${WEBHOOK_URL:-}"
WEBHOOK_SECRET="${WEBHOOK_SECRET:-}"
REPOSITORY="${REPOSITORY:-$DEFAULT_REPOSITORY}"

# Hilfsfunktionen
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

# Hilfe anzeigen
show_help() {
    cat <<EOF
Verwendung: $0 [OPTIONS]

Test-Script für den Deployment-Webhook

Optionen:
  -u URL        Webhook URL (z.B. https://server.example.com/deploy-webhook)
  -s SECRET     Webhook Secret (muss mit Server übereinstimmen)
  -h            Diese Hilfe anzeigen

Umgebungsvariablen:
  WEBHOOK_URL      Webhook URL
  WEBHOOK_SECRET   Webhook Secret
  REPOSITORY       Repository Name (Standard: $DEFAULT_REPOSITORY)

Beispiel:
  $0 -u https://testserver.example.com/deploy-webhook -s mein-secret-hier

  oder mit Umgebungsvariablen:
  
  export WEBHOOK_URL="https://testserver.example.com/deploy-webhook"
  export WEBHOOK_SECRET="mein-secret-hier"
  $0

EOF
}

# Argumente parsen
while getopts "u:s:h" opt; do
    case $opt in
        u)
            WEBHOOK_URL="$OPTARG"
            ;;
        s)
            WEBHOOK_SECRET="$OPTARG"
            ;;
        h)
            show_help
            exit 0
            ;;
        \?)
            log_error "Ungültige Option: -$OPTARG"
            show_help
            exit 1
            ;;
    esac
done

# Prüfen ob URL und Secret gesetzt sind
if [ -z "$WEBHOOK_URL" ]; then
    log_error "Webhook URL nicht gesetzt!"
    log_info "Verwenden Sie -u URL oder setzen Sie WEBHOOK_URL"
    show_help
    exit 1
fi

if [ -z "$WEBHOOK_SECRET" ]; then
    log_error "Webhook Secret nicht gesetzt!"
    log_info "Verwenden Sie -s SECRET oder setzen Sie WEBHOOK_SECRET"
    show_help
    exit 1
fi

# Prüfen ob curl installiert ist
if ! command -v curl &> /dev/null; then
    log_error "curl ist nicht installiert!"
    exit 1
fi

# Prüfen ob openssl installiert ist
if ! command -v openssl &> /dev/null; then
    log_error "openssl ist nicht installiert!"
    exit 1
fi

# Prüfen ob jq installiert ist (optional, für schöne Ausgabe)
JQ_AVAILABLE=false
if command -v jq &> /dev/null; then
    JQ_AVAILABLE=true
fi

log_info "=========================================="
log_info "Webhook Deployment Test"
log_info "=========================================="
log_info "URL: $WEBHOOK_URL"
log_info "Secret: ${WEBHOOK_SECRET:0:8}... (gekürzt)"
log_info ""

# Test-Payload erstellen
TIMESTAMP=$(date +%s)
# Repository name can be set via environment variable or defaults to example
REPOSITORY="${REPOSITORY:-your-user/your-repo}"
PAYLOAD=$(cat <<EOF
{
  "ref": "refs/heads/develop",
  "repository": "$REPOSITORY",
  "commit": "test-$(date +%Y%m%d-%H%M%S)",
  "commit_message": "Test deployment from test script",
  "pusher": "test-script",
  "timestamp": $TIMESTAMP
}
EOF
)

log_info "Payload erstellt:"
if [ "$JQ_AVAILABLE" = true ]; then
    echo "$PAYLOAD" | jq .
else
    echo "$PAYLOAD"
fi
log_info ""

# Signatur generieren
log_info "Generiere HMAC-SHA256 Signatur..."
SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$WEBHOOK_SECRET" | sed 's/^.* //')
log_info "Signatur: sha256=$SIGNATURE"
log_info ""

# Webhook-Request senden
log_info "Sende Webhook-Request..."
log_info ""

RESPONSE_FILE=$(mktemp)
HTTP_CODE=$(curl -s -o "$RESPONSE_FILE" -w "%{http_code}" \
    -X POST \
    -H "Content-Type: application/json" \
    -H "X-Hub-Signature-256: sha256=$SIGNATURE" \
    -H "X-GitHub-Event: push" \
    -H "User-Agent: Webhook-Test-Script" \
    --data "$PAYLOAD" \
    "$WEBHOOK_URL")

log_info "HTTP Status Code: $HTTP_CODE"
log_info ""
log_info "Response Body:"
if [ "$JQ_AVAILABLE" = true ]; then
    cat "$RESPONSE_FILE" | jq . || cat "$RESPONSE_FILE"
else
    cat "$RESPONSE_FILE"
fi
log_info ""

# Aufräumen
rm -f "$RESPONSE_FILE"

# Ergebnis bewerten
if [ "$HTTP_CODE" -ge 200 ] && [ "$HTTP_CODE" -lt 300 ]; then
    log_success "✅ Webhook-Test erfolgreich!"
    log_success "Der Deployment-Prozess sollte jetzt auf dem Server laufen."
    log_info ""
    log_info "Nächste Schritte:"
    log_info "1. Überprüfen Sie die Logs auf dem Server:"
    log_info "   tail -f /var/www/webhook/webhook-deploy.log"
    log_info ""
    log_info "2. Prüfen Sie den Docker-Container-Status:"
    log_info "   docker compose ps"
    log_info ""
    log_info "3. Überwachen Sie die Deployment-Logs:"
    log_info "   docker compose logs -f"
    exit 0
else
    log_error "❌ Webhook-Test fehlgeschlagen!"
    log_error "HTTP Status Code: $HTTP_CODE"
    log_info ""
    log_info "Mögliche Ursachen:"
    log_info "1. Webhook URL ist falsch oder nicht erreichbar"
    log_info "2. Webhook Secret stimmt nicht überein"
    log_info "3. Server-Konfiguration ist fehlerhaft"
    log_info "4. Firewall blockiert den Zugriff"
    log_info ""
    log_info "Debugging-Schritte:"
    log_info "1. Testen Sie die URL direkt im Browser: $WEBHOOK_URL"
    log_info "2. Prüfen Sie die Server-Logs:"
    log_info "   - nginx/apache error logs"
    log_info "   - PHP-FPM logs"
    log_info "   - /var/www/webhook/webhook-deploy.log"
    log_info "3. Testen Sie mit curl ohne Signatur:"
    log_info "   curl -v $WEBHOOK_URL"
    exit 1
fi
