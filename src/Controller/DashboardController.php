<?php
declare(strict_types=1);
/**
 * DashboardController.php
 *
 * Dieser Controller ist für die Anzeige des Dashboards (Startseite) zuständig.
 * Er zeigt eine Zusammenfassung der aktuellen Aktivitäten und Status des
 * Ticket-Mail-Systems an, insbesondere eine Liste der zuletzt gesendeten E-Mails.
 *
 * @package App\Controller
 */

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\EmailSentRepository;

/**
 * Controller für das Dashboard (Startseite) der Anwendung
 */
final class DashboardController extends AbstractController
{
    /**
     * Repository für den Zugriff auf gesendete E-Mails
     *
     * @var EmailSentRepository
     */
    private EmailSentRepository $emailSentRepository;

    private \App\Service\StatisticsService $statisticsService;

    /**
     * Konstruktor mit Dependency Injection für das E-Mail-Repository
     *
     * @param EmailSentRepository $emailSentRepository Repository für E-Mail-Protokolle
     */
    public function __construct(EmailSentRepository $emailSentRepository, \App\Service\StatisticsService $statisticsService)
    {
        $this->emailSentRepository = $emailSentRepository;
        $this->statisticsService = $statisticsService;
    }

    /**
     * Zeigt das Dashboard mit den letzten E-Mail-Versandaktionen an
     *
     * Diese Methode ruft die zuletzt gesendeten E-Mails aus der Datenbank ab
     * und übergibt sie zusammen mit Statistiken an das Template zur Anzeige auf der Startseite.
     *
     * @return Response Die gerenderte Dashboard-Seite
     */
    #[Route('/', name: 'dashboard')]
    public function index(): Response
    {
        // Hole die letzten Versandaktionen (die neuesten 10 E-Mails)
        $recentEmails = $this->emailSentRepository->findBy(
            [],
            ['timestamp' => 'DESC'],
            10
        );

        // Hole die E-Mail-Statistiken
        $statistics = $this->emailSentRepository->getEmailStatistics();

        // Hole die monatlichen Benutzer- und Ticketstatistiken über den Service (DTOs)
        $monthlyDomainStatistics = $this->statisticsService->getMonthlyUserStatisticsByDomain();
        $monthlyTicketStatistics = $this->statisticsService->getMonthlyTicketStatisticsByDomain();

        // Render das Dashboard-Template mit den abgerufenen Daten
        return $this->render('dashboard/index.html.twig', [
            'recentEmails' => $recentEmails,
            'statistics' => $statistics,
            'monthlyDomainStatistics' => $monthlyDomainStatistics,
            'monthlyTicketStatistics' => $monthlyTicketStatistics,
        ]);
    }

    /**
     * API-Endpunkt für E-Mail-Statistiken
     *
     * Diese Route gibt die E-Mail-Statistiken als JSON zurück,
     * nützlich für AJAX-basierte Updates oder externe Systeme.
     *
     * @return JsonResponse JSON-Response mit Statistiken
     */
    #[Route('/api/statistics', name: 'api_statistics')]
    public function statistics(): JsonResponse
    {
        $statistics = $this->emailSentRepository->getEmailStatistics();

        return new JsonResponse($statistics);
    }

    /**
     * Löscht den Statistik-Cache manuell
     *
     * Diese Route wird aufgerufen, wenn der Benutzer auf den "Cache löschen" Button
     * im Dashboard klickt. Sie löscht alle Cache-Einträge für monatliche Statistiken
     * (sowohl Benutzer- als auch Ticketstatistiken für alle Monatsbereiche 1-12),
     * sodass beim nächsten Abruf neue Daten aus der Datenbank geladen werden.
     * 
     * Nach dem Löschen wird eine Erfolgsmeldung angezeigt und der Benutzer wird
     * zurück zum Dashboard weitergeleitet.
     *
     * @return Response Redirect zur Dashboard-Seite mit Erfolgsmeldung
     */
    #[Route('/cache/clear', name: 'cache_clear')]
    public function clearCache(): Response
    {
        $this->statisticsService->clearCache();
        
        $this->addFlash('success', 'Statistik-Cache wurde erfolgreich gelöscht');
        
        return $this->redirectToRoute('dashboard');
    }
}