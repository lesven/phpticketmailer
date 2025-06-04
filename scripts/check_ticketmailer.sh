#!/bin/bash
# check_ticketmailer.sh
# This script checks the health of the ticketmailer application
# and can be used as a cron job or scheduled task

# Configuration
BASE_URL="http://localhost:8090"  # Change this to your actual host/port
HEALTH_ENDPOINT="/monitoring/health"
SLACK_WEBHOOK_URL=""  # Optional: Add your Slack webhook URL here to get notifications
EMAIL_RECIPIENT=""    # Optional: Add email to receive notifications

# Function to send a notification
send_notification() {
    local status=$1
    local message=$2
    
    echo "[$(date)] $status: $message"
    
    # Send email notification if configured
    if [ -n "$EMAIL_RECIPIENT" ]; then
        echo "$message" | mail -s "Ticketmailer System $status" $EMAIL_RECIPIENT
    fi
    
    # Send Slack notification if configured
    if [ -n "$SLACK_WEBHOOK_URL" ]; then
        local color="good"
        if [ "$status" != "OK" ]; then
            color="danger"
        fi
        
        curl -s -X POST -H 'Content-type: application/json' \
            --data "{\"attachments\":[{\"color\":\"$color\",\"title\":\"Ticketmailer System $status\",\"text\":\"$message\"}]}" \
            $SLACK_WEBHOOK_URL
    fi
}

# Make the request to the health endpoint
echo "Checking system health at $BASE_URL$HEALTH_ENDPOINT..."
response=$(curl -s -w "\n%{http_code}" "$BASE_URL$HEALTH_ENDPOINT")

# Extract HTTP status code
http_code=$(echo "$response" | tail -n1)
json_response=$(echo "$response" | sed '$d')  # Remove the last line (status code)

# Check if the request was successful
if [ "$http_code" -ne 200 ]; then
    send_notification "ERROR" "Health check failed with HTTP code $http_code. Response: $json_response"
    exit 1
fi

# Parse the JSON response (requires jq)
if command -v jq &> /dev/null; then
    status=$(echo "$json_response" | jq -r '.status')
    
    if [ "$status" = "ok" ]; then
        send_notification "OK" "All systems operational"
        exit 0
    else
        # Extract detailed error information
        db_status=$(echo "$json_response" | jq -r '.checks.database.status')
        web_status=$(echo "$json_response" | jq -r '.checks.webserver.status')
        container_status=$(echo "$json_response" | jq -r '.checks.containers.status')
        
        error_msg="System health check failed. "
        
        if [ "$db_status" != "ok" ]; then
            db_error=$(echo "$json_response" | jq -r '.checks.database.error // "Unknown database error"')
            error_msg+="Database: $db_error. "
        fi
        
        if [ "$web_status" != "ok" ]; then
            web_error=$(echo "$json_response" | jq -r '.checks.webserver.error // "Unknown webserver error"')
            error_msg+="Webserver: $web_error. "
        fi
        
        if [ "$container_status" != "ok" ]; then
            container_error=$(echo "$json_response" | jq -r '.checks.containers.error // "Unknown container error"')
            error_msg+="Containers: $container_error"
        fi
        
        send_notification "ERROR" "$error_msg"
        exit 1
    fi
else
    # Fallback if jq is not available
    if [[ "$json_response" == *"\"status\":\"ok\""* ]]; then
        send_notification "OK" "All systems operational"
        exit 0
    else
        send_notification "ERROR" "System health check failed. Response: $json_response"
        exit 1
    fi
fi
