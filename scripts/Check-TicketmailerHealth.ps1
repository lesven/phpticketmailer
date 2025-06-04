# Check-TicketmailerHealth.ps1
# This PowerShell script checks the health of the ticketmailer application
# and can be used as a scheduled task on Windows systems

# Configuration
$BaseUrl = "http://localhost:8090"  # Change this to your actual host/port
$HealthEndpoint = "/monitoring/health"
$EmailRecipient = ""  # Optional: Add email to receive notifications
$SmtpServer = ""      # Optional: SMTP server for sending emails

# Function to send a notification
function Send-Notification {
    param (
        [string]$Status,
        [string]$Message
    )
    
    Write-Host "[$([DateTime]::Now)] $Status`: $Message"
    
    # Send email notification if configured
    if ($EmailRecipient -and $SmtpServer) {
        $Subject = "Ticketmailer System $Status"
        $EmailParams = @{
            To = $EmailRecipient
            From = "monitoring@ticketmailer.local"
            Subject = $Subject
            Body = $Message
            SmtpServer = $SmtpServer
        }
        
        try {
            Send-MailMessage @EmailParams
            Write-Host "Notification email sent to $EmailRecipient"
        } catch {
            Write-Error "Failed to send notification email: $_"
        }
    }
    
    # You could also add other notification methods here (Teams webhook, etc.)
}

# Make the request to the health endpoint
Write-Host "Checking system health at $BaseUrl$HealthEndpoint..."

try {
    $Response = Invoke-RestMethod -Uri "$BaseUrl$HealthEndpoint" -Method Get
    
    if ($Response.status -eq "ok") {
        Send-Notification -Status "OK" -Message "All systems operational"
        exit 0
    } else {
        # Build error message from components
        $ErrorMsg = "System health check failed. "
        
        if ($Response.checks.database.status -ne "ok") {
            $DbError = if ($Response.checks.database.error) { $Response.checks.database.error } else { "Unknown database error" }
            $ErrorMsg += "Database: $DbError. "
        }
        
        if ($Response.checks.webserver.status -ne "ok") {
            $WebError = if ($Response.checks.webserver.error) { $Response.checks.webserver.error } else { "Unknown webserver error" }
            $ErrorMsg += "Webserver: $WebError. "
        }
        
        if ($Response.checks.containers.status -ne "ok") {
            $ContainerError = if ($Response.checks.containers.error) { $Response.checks.containers.error } else { "Unknown container error" }
            $ErrorMsg += "Containers: $ContainerError"
            
            # List problematic containers
            $ProblemContainers = $Response.checks.containers.containers.PSObject.Properties | 
                Where-Object { $_.Value.status -ne "ok" } |
                ForEach-Object { $_.Name }
            
            if ($ProblemContainers) {
                $ErrorMsg += " Problem containers: $($ProblemContainers -join ', ')"
            }
        }
        
        Send-Notification -Status "ERROR" -Message $ErrorMsg
        exit 1
    }
} catch {
    $StatusCode = $_.Exception.Response.StatusCode.value__
    $ErrorMessage = $_.Exception.Message
    
    Send-Notification -Status "ERROR" -Message "Health check failed with error: $ErrorMessage (Status code: $StatusCode)"
    exit 1
}
