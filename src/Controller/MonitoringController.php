<?php
/**
 * MonitoringController.php
 * 
 * Dieser Controller stellt Endpunkte für das Zabbix-Monitoring bereit.
 * Er prüft den Zustand der Datenbank und den Lesezugriff auf wichtige Tabellen.
 * 
 * @package App\Controller
 */

namespace App\Controller;

use App\Service\MonitoringService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller für das Zabbix-Monitoring
 */
#[Route('/monitoring')]
class MonitoringController extends AbstractController
{
    /**
     * Service für die Monitoring-Funktionalitäten
     * 
     * @var MonitoringService
     */
    private $monitoringService;
    
    /**
     * Konstruktor
     * 
     * @param MonitoringService $monitoringService
     */
    public function __construct(MonitoringService $monitoringService)
    {
        $this->monitoringService = $monitoringService;
    }
    
    /**
     * Zeigt die Web-Oberfläche für das Monitoring an
     * 
     * @return Response
     */
    #[Route('', name: 'app_monitoring', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('monitoring/index.html.twig');
    }

    /**
     * Überprüft den allgemeinen Systemstatus
     * Diese Methode ist als Hauptendpunkt für Zabbix gedacht
     * 
     * @return Response
     */
    #[Route('/health', name: 'app_monitoring_health', methods: ['GET'])]
    public function health(): Response
    {
        $result = $this->monitoringService->checkSystemHealth();
        
        // Standardmäßig wird JSON zurückgegeben für einfache Zabbix-Integration
        return $this->json($result);
    }    /**
     * Überprüft speziell die Datenbankverbindung und den Tabellenzugriff
     * 
     * @return Response
     */
    #[Route('/database', name: 'app_monitoring_database', methods: ['GET'])]
    public function database(): Response
    {
        $result = $this->monitoringService->checkDatabase();
        return $this->json($result);
    }
}
