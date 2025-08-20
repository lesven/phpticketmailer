<?php
/**
 * MonitoringService.php
 * 
 * Dieser Service stellt Methoden zur Überprüfung der Systemgesundheit bereit.
 * Insbesondere prüft er die Erreichbarkeit der Datenbank für das Zabbix-Monitoring.
 * 
 * @package App\Service
 */

namespace App\Service;

use App\Repository\UserRepository;
use App\Repository\EmailSentRepository;
use App\Repository\CsvFieldConfigRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;

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
    private $csvFieldConfigRepository;

    /**
     * @var string|null
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
    ?string $baseUrl = null
    ) {
        $this->connection = $connection;
        $this->userRepository = $userRepository;
        $this->emailSentRepository = $emailSentRepository;
        $this->csvFieldConfigRepository = $csvFieldConfigRepository;
        
        // Standardmäßig die aktuelle Host-Umgebung verwenden, falls keine URL angegeben ist
        $this->baseUrl = $baseUrl ?? 'http://localhost:8090';
    }    /**
     * Führt einen vollständigen Systemgesundheitscheck durch
     * Der Health-Check prüft nur die Datenbank, da dies die einzige
     * relevante Überprüfung gemäß den aktualisierten Anforderungen ist
     * 
     * @return array
     */
    public function checkSystemHealth(): array
    {
        $dbCheck = $this->checkDatabase();
        
        return [
            'status' => $dbCheck['status'],
            'timestamp' => new \DateTime(),
            'checks' => [
                'database' => $dbCheck
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
}
