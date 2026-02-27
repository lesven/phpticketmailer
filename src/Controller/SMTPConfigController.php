<?php
/**
 * SMTPConfigController.php
 * 
 * Dieser Controller ist für die Verwaltung der SMTP-Konfiguration zuständig.
 * Er bietet Funktionalität zum Anzeigen, Bearbeiten und Testen der SMTP-Server-Einstellungen
 * für den E-Mail-Versand des Ticket-Systems.
 * 
 * @package App\Controller
 */

namespace App\Controller;

use App\Entity\SMTPConfig;
use App\Form\SMTPConfigType;
use App\Repository\SMTPConfigRepository;
use App\Service\EmailTransportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller zur Verwaltung der SMTP-Konfiguration
 */
#[Route('/smtp-config')]
class SMTPConfigController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SMTPConfigRepository $smtpConfigRepository,
        private readonly EmailTransportService $emailTransportService,
    ) {
    }
    
    /**
     * Bearbeitet die SMTP-Konfiguration und ermöglicht das Senden einer Test-E-Mail
     * 
     * Diese Methode zeigt ein Formular zur Bearbeitung der SMTP-Konfiguration an.
     * Wenn noch keine Konfiguration existiert, wird eine neue mit Standardwerten erstellt.
     * Nach dem Speichern kann eine Test-E-Mail gesendet werden, um die Konfiguration zu überprüfen.
     * 
     * @param Request $request Die HTTP-Anfrage
     * @param MailerInterface $mailer Der Mailer-Service
     * @return Response Die gerenderte Seite zur Bearbeitung der SMTP-Konfiguration
     */
    #[Route('/', name: 'smtp_config_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        // Aktuelle Konfiguration aus der Datenbank laden
        $config = $this->smtpConfigRepository->getConfig();
          // Falls keine Konfiguration existiert, erstelle eine neue mit Standardwerten
        if (!$config) {
            $config = new SMTPConfig();
            // Standardwerte setzen
            $config->setHost('localhost');
            $config->setPort(25);
            $config->setSenderEmail('noreply@example.com');
            $config->setSenderName('Ticket-System');
            $config->setTicketBaseUrl('https://www.ticket.de');
            $config->setUseTLS(false);
            $config->setVerifySSL(true);
        }
        
        // Formular erstellen und Request verarbeiten
        $form = $this->createForm(SMTPConfigType::class, $config);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            // Test-E-Mail-Adresse aus der Anfrage holen
            $testEmail = $request->request->get('test_email');
            
            // Konfiguration in der Datenbank speichern
            $this->entityManager->persist($config);
            $this->entityManager->flush();
            
            // Wenn eine Test-Email angegeben wurde, senden wir eine Testmail
            if (!empty($testEmail)) {
                try {
                    $this->emailTransportService->sendTestEmail(
                        $config->getDSN(),
                        (string) $config->getSenderEmail(),
                        $testEmail
                    );
                    
                    $this->addFlash('success', 'Die SMTP-Konfiguration wurde gespeichert und die Test-E-Mail erfolgreich an ' . $testEmail . ' gesendet.');
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Die Konfiguration wurde gespeichert, aber die Test-E-Mail konnte nicht gesendet werden: ' . $e->getMessage());
                }
            } else {
                // Erfolgsbenachrichtigung anzeigen (ohne Test-E-Mail)
                $this->addFlash('success', 'SMTP-Konfiguration wurde erfolgreich gespeichert.');
            }
            
            // Zurück zur Edit-Seite leiten
            return $this->redirectToRoute('smtp_config_edit');
        }
        
        // Template rendern mit dem Formular
        return $this->render('smtp_config/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}