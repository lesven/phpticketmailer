<?php
/**
 * Deployment Webhook Receiver
 * 
 * This script receives webhook requests from GitHub Actions and triggers
 * the deployment process by executing 'make deploy'.
 * 
 * SECURITY NOTICE:
 * - This script validates webhook signatures using HMAC-SHA256
 * - The webhook secret must be configured in both GitHub and this script
 * - Only accepts requests from configured sources
 * 
 * SETUP:
 * 1. Place this file in a web-accessible directory (e.g., /var/www/webhook/)
 * 2. Configure your web server (nginx/apache) to serve this script via HTTPS
 * 3. Set the WEBHOOK_SECRET in your .env file or environment variables
 * 4. Ensure the web server user has permission to execute 'make deploy'
 * 5. Add the webhook URL to GitHub repository secrets as DEPLOY_WEBHOOK_URL
 * 6. Add the webhook secret to GitHub repository secrets as DEPLOY_WEBHOOK_SECRET
 * 
 * Example nginx configuration:
 * 
 * server {
 *     listen 443 ssl;
 *     server_name your-server.example.com;
 *     
 *     ssl_certificate /path/to/certificate.crt;
 *     ssl_certificate_key /path/to/private.key;
 *     
 *     location /deploy-webhook {
 *         alias /var/www/webhook;
 *         index webhook-receiver.php;
 *         
 *         location ~ \.php$ {
 *             include snippets/fastcgi-php.conf;
 *             fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
 *         }
 *     }
 * }
 */

// Set error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Don't display errors to client
ini_set('log_errors', '1');

// Define log file path
define('LOG_FILE', __DIR__ . '/webhook-deploy.log');

// Define project root path - adjust this to your actual project path
define('PROJECT_ROOT', '/var/www/phpticketmailer'); // CHANGE THIS TO YOUR PROJECT PATH

/**
 * Log message to file with timestamp
 */
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$level}] {$message}\n";
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND);
}

/**
 * Send JSON response and exit
 */
function sendResponse($statusCode, $message, $data = null) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    
    $response = [
        'status' => $statusCode >= 200 && $statusCode < 300 ? 'success' : 'error',
        'message' => $message,
        'timestamp' => time()
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

/**
 * Verify webhook signature
 */
function verifySignature($payload, $signature, $secret) {
    if (empty($signature)) {
        return false;
    }
    
    // Remove 'sha256=' prefix if present
    $signature = str_replace('sha256=', '', $signature);
    
    // Calculate expected signature
    $expectedSignature = hash_hmac('sha256', $payload, $secret);
    
    // Use hash_equals to prevent timing attacks
    return hash_equals($expectedSignature, $signature);
}

/**
 * Execute deployment
 */
function executeDeploy() {
    logMessage('Starting deployment process...');
    
    // Change to project directory
    if (!is_dir(PROJECT_ROOT)) {
        logMessage('Project directory does not exist: ' . PROJECT_ROOT, 'ERROR');
        sendResponse(500, 'Project directory not found');
    }
    
    chdir(PROJECT_ROOT);
    
    // Build the command - redirect output to both stdout and log file
    $command = 'make deploy 2>&1';
    $output = [];
    $returnCode = 0;
    
    logMessage('Executing: ' . $command);
    
    // Execute the deployment
    exec($command, $output, $returnCode);
    
    // Log output
    $outputStr = implode("\n", $output);
    logMessage("Deployment output:\n" . $outputStr);
    
    if ($returnCode === 0) {
        logMessage('Deployment completed successfully', 'SUCCESS');
        sendResponse(200, 'Deployment completed successfully', [
            'return_code' => $returnCode,
            'output_lines' => count($output)
        ]);
    } else {
        logMessage('Deployment failed with return code: ' . $returnCode, 'ERROR');
        logMessage('Error output: ' . $outputStr, 'ERROR');
        sendResponse(500, 'Deployment failed', [
            'return_code' => $returnCode,
            'error' => 'See log file for details'
        ]);
    }
}

// Main execution
try {
    logMessage('Webhook request received from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    
    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        logMessage('Invalid request method: ' . $_SERVER['REQUEST_METHOD'], 'WARNING');
        sendResponse(405, 'Method not allowed');
    }
    
    // Get webhook secret from environment or configuration file
    $webhookSecret = getenv('WEBHOOK_SECRET');
    
    // If not in environment, try to load from .env file
    if (empty($webhookSecret)) {
        $envFile = PROJECT_ROOT . '/.env';
        if (file_exists($envFile)) {
            $envContent = file_get_contents($envFile);
            if (preg_match('/WEBHOOK_SECRET=(.+)/', $envContent, $matches)) {
                $webhookSecret = trim($matches[1]);
            }
        }
    }
    
    if (empty($webhookSecret)) {
        logMessage('Webhook secret not configured', 'ERROR');
        sendResponse(500, 'Webhook secret not configured');
    }
    
    // Get request body
    $payload = file_get_contents('php://input');
    
    if (empty($payload)) {
        logMessage('Empty payload received', 'WARNING');
        sendResponse(400, 'Empty payload');
    }
    
    // Get signature from header
    $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    
    // Verify signature
    if (!verifySignature($payload, $signature, $webhookSecret)) {
        logMessage('Invalid signature - authentication failed', 'WARNING');
        logMessage('Received signature: ' . $signature, 'DEBUG');
        sendResponse(401, 'Invalid signature');
    }
    
    logMessage('Signature verified successfully');
    
    // Parse JSON payload
    $data = json_decode($payload, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        logMessage('Invalid JSON payload: ' . json_last_error_msg(), 'ERROR');
        sendResponse(400, 'Invalid JSON payload');
    }
    
    // Log deployment details
    logMessage('Deployment triggered by: ' . ($data['pusher'] ?? 'unknown'));
    logMessage('Repository: ' . ($data['repository'] ?? 'unknown'));
    logMessage('Commit: ' . ($data['commit'] ?? 'unknown'));
    logMessage('Ref: ' . ($data['ref'] ?? 'unknown'));
    
    // Execute deployment
    executeDeploy();
    
} catch (Exception $e) {
    logMessage('Exception occurred: ' . $e->getMessage(), 'ERROR');
    logMessage('Stack trace: ' . $e->getTraceAsString(), 'ERROR');
    sendResponse(500, 'Internal server error: ' . $e->getMessage());
}
