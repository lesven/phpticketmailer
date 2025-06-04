<?php
/**
 * MonitoringService.php
 * 
 * Dieser Service stellt Methoden zur Überprüfung der Systemgesundheit bereit.
 * Insbesondere prüft er die Erreichbarkeit der Datenbank, des Webservers
 * und den Status der Docker-Container.
 * 
 * @package App\Service
 */

namespace App\Service;

use App\Repository\UserRepository;
use App\Repository\EmailSentRepository;
use App\Repository\CsvFieldConfigRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Symfony\Component\Process\Process;

/**
 * Service für die Überprüfung der Systemgesundheit
 */
class MonitoringService
{
    /**
     * @var Connection
     */
    private $connection;
    
    /**
     * @var UserRepository
     */
    private $userRepository;
    
    /**
     * @var EmailSentRepository
     */
    private $emailSentRepository;
    
    /**
     * @var CsvFieldConfigRepository
     */
    private $csvFieldConfigRepository;    /**
     * @var string 
     */
    private $baseUrl;

    /**
     * Konstruktor
     * 
     * @param Connection $connection
     * @param UserRepository $userRepository
     * @param EmailSentRepository $emailSentRepository
     * @param CsvFieldConfigRepository $csvFieldConfigRepository
     * @param string $baseUrl
     */
    public function __construct(
        Connection $connection, 
        UserRepository $userRepository,
        EmailSentRepository $emailSentRepository,
        CsvFieldConfigRepository $csvFieldConfigRepository,
        string $baseUrl = null
    ) {
        $this->connection = $connection;
        $this->userRepository = $userRepository;
        $this->emailSentRepository = $emailSentRepository;
        $this->csvFieldConfigRepository = $csvFieldConfigRepository;
        
        // Standardmäßig die aktuelle Host-Umgebung verwenden, falls keine URL angegeben ist
        $this->baseUrl = $baseUrl ?? 'http://localhost:8090';
    }

    /**
     * Führt einen vollständigen Systemgesundheitscheck durch
     * 
     * @return array
     */
    public function checkSystemHealth(): array
    {
        $dbCheck = $this->checkDatabase();
        $webCheck = $this->checkWebserver();
        $containerCheck = $this->checkContainers();
        
        $isHealthy = $dbCheck['status'] === 'ok' && 
                     $webCheck['status'] === 'ok' && 
                     $containerCheck['status'] === 'ok';
        
        return [
            'status' => $isHealthy ? 'ok' : 'error',
            'timestamp' => new \DateTime(),
            'checks' => [
                'database' => $dbCheck,
                'webserver' => $webCheck,
                'containers' => $containerCheck
            ]
        ];
    }

    /**
     * Überprüft die Datenbankverbindung und den Tabellenzugriff
     * 
     * @return array
     */
    public function checkDatabase(): array
    {
        $result = [
            'status' => 'ok',
            'tables' => [],
            'error' => null
        ];
        
        try {
            // Überprüfe die Verbindung zur Datenbank
            $this->connection->connect();
            
            // Überprüfe den Zugriff auf wichtige Tabellen
            $tablesToCheck = [
                'users' => $this->userRepository,
                'emails_sent' => $this->emailSentRepository,
                'csv_field_config' => $this->csvFieldConfigRepository
            ];
            
            foreach ($tablesToCheck as $tableName => $repository) {
                try {
                    // Versuche eine einfache Zählung durchzuführen
                    $count = $repository->createQueryBuilder('t')
                        ->select('COUNT(t.id)')
                        ->getQuery()
                        ->getSingleScalarResult();
                    
                    $result['tables'][$tableName] = [
                        'status' => 'ok',
                        'recordCount' => $count
                    ];
                } catch (\Exception $e) {
                    $result['tables'][$tableName] = [
                        'status' => 'error',
                        'error' => $e->getMessage()
                    ];
                    $result['status'] = 'error';
                }
            }
        } catch (DBALException $e) {
            $result['status'] = 'error';
            $result['error'] = 'Datenbankverbindung fehlgeschlagen: ' . $e->getMessage();
        }
        
        return $result;
    }

    /**
     * Überprüft die externe Erreichbarkeit des Webservers
     * 
     * @return array
     */
    public function checkWebserver(): array
    {
        $result = [
            'status' => 'ok',
            'url' => $this->baseUrl,
            'error' => null,
            'responseTime' => null
        ];
        
        try {
            $startTime = microtime(true);
            
            // Konfigurieren des Kontexts für die Anfrage
            $opts = [
                'http' => [
                    'method' => 'GET',
                    'timeout' => 5.0,
                    'ignore_errors' => true
                ]
            ];
            $context = stream_context_create($opts);
            
            // Anfrage durchführen
            $response = @file_get_contents($this->baseUrl, false, $context);
            $responseTime = round((microtime(true) - $startTime) * 1000); // in ms
            $result['responseTime'] = $responseTime;
            
            // HTTP-Status-Code aus den Header-Informationen extrahieren
            if (isset($http_response_header) && is_array($http_response_header) && count($http_response_header) > 0) {
                preg_match('/^HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
                $statusCode = isset($matches[1]) ? (int) $matches[1] : 0;
                $result['statusCode'] = $statusCode;
                
                if ($statusCode < 200 || $statusCode >= 400) {
                    $result['status'] = 'error';
                    $result['error'] = 'Webserver erreichbar, aber unerwarteter Status-Code: ' . $statusCode;
                }
            } else {
                // Keine HTTP-Header gefunden, was auf einen Fehler hinweist
                $result['status'] = 'error';
                $result['error'] = 'Keine gültige HTTP-Antwort erhalten';
            }
        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['error'] = 'Webserver nicht erreichbar: ' . $e->getMessage();
        }
        
        return $result;
    }

    /**
     * Überprüft den Status der Docker-Container
     * 
     * @return array
     */
    public function checkContainers(): array
    {
        $result = [
            'status' => 'ok',
            'containers' => [],
            'error' => null
        ];
        
        // Container, die wir überwachen möchten
        $containersToCheck = [
            'ticketumfrage_php',
            'ticketumfrage_webserver',
            'ticketumfrage_database',
            'ticketumfrage_mailhog',
            'ticketumfrage_mailserver'
        ];
        
        try {
            // Docker-Befehle ausführen, um den Status der Container zu überprüfen
            $process = new Process(['docker', 'ps', '--format', '{{.Names}}|{{.Status}}|{{.Health}}']);
            $process->run();
            
            if (!$process->isSuccessful()) {
                throw new \RuntimeException($process->getErrorOutput());
            }
            
            $output = $process->getOutput();
            $containerData = [];
            
            foreach (explode("\n", trim($output)) as $line) {
                if (empty($line)) continue;
                
                list($name, $status, $health) = explode('|', $line . '||');
                $containerData[$name] = [
                    'status' => $status,
                    'health' => $health ?: 'N/A'
                ];
            }
            
            // Überprüfe jeden Container
            foreach ($containersToCheck as $containerName) {
                if (isset($containerData[$containerName])) {
                    $container = $containerData[$containerName];
                    $isRunning = strpos($container['status'], 'Up') === 0;
                    $isHealthy = $container['health'] === 'healthy' || $container['health'] === 'N/A';
                    
                    $result['containers'][$containerName] = [
                        'status' => $isRunning && $isHealthy ? 'ok' : 'error',
                        'running' => $isRunning,
                        'health' => $container['health'],
                        'fullStatus' => $container['status']
                    ];
                    
                    if (!$isRunning || !$isHealthy) {
                        $result['status'] = 'error';
                    }
                } else {
                    $result['containers'][$containerName] = [
                        'status' => 'error',
                        'running' => false,
                        'health' => 'N/A',
                        'fullStatus' => 'Container nicht gefunden'
                    ];
                    $result['status'] = 'error';
                }
            }
        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['error'] = 'Fehler bei der Überprüfung der Docker-Container: ' . $e->getMessage();
        }
        
        return $result;
    }
}
