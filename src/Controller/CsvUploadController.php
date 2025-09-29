<?php

namespace App\Controller;

use App\Entity\CsvFieldConfig;
use App\Form\CsvUploadType;
use App\Repository\CsvFieldConfigRepository;
use App\Service\CsvUploadOrchestrator;
use App\Service\SessionManager;
use App\Service\UploadResult;
use App\Service\UnknownUsersResult;
use App\Service\EmailService;
use App\Service\EmailNormalizer;
use App\Exception\TicketMailerException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Controller für das Hochladen und Verarbeiten von CSV-Dateien mit Ticketdaten
 * 
 * Dieser Controller verwaltet den Prozess vom Hochladen der CSV-Datei,
 * über die Bearbeitung unbekannter Benutzer bis zum Versand der Ticket-E-Mails.
 */
class CsvUploadController extends AbstractController
{    /**
     * Konstruktor zum Injection der benötigten Abhängigkeiten
     */
    public function __construct(
        private readonly CsvUploadOrchestrator $csvUploadOrchestrator,
        private readonly SessionManager $sessionManager,
        private readonly EmailService $emailService,
        private readonly CsvFieldConfigRepository $csvFieldConfigRepository,
        private readonly EmailNormalizer $emailNormalizer,
        private readonly ParameterBagInterface $params
    ) {
    }
      /**
     * Zeigt das Formular zum Hochladen einer CSV-Datei an und verarbeitet die Übermittlung
     */
    #[Route('/upload', name: 'csv_upload')]
    public function upload(Request $request): Response
    {
        // Aktuelle CSV-Konfiguration laden
        $csvFieldConfig = $this->csvFieldConfigRepository->getCurrentConfig();
        
        $form = $this->createForm(CsvUploadType::class);
        $form->get('csvFieldConfig')->setData($csvFieldConfig);
        
        // Standard-Test-E-Mail als Vorgabe setzen
        $defaultTestEmail = $this->params->get('app.test_email') ?? 'test@example.com';
        $form->get('testEmail')->setData($defaultTestEmail);
        
        $form->handleRequest($request);        if ($form->isSubmitted() && $form->isValid()) {
            $csvFile = $form->get('csvFile')->getData();
            $testMode = $form->get('testMode')->getData();
            $forceResend = $form->get('forceResend')->getData();
            $testEmail = $form->get('testEmail')->getData();
            $updatedConfig = $form->get('csvFieldConfig')->getData();
            
            // Test-E-Mail in Session speichern für spätere Verwendung
            if ($testMode) {
                $this->sessionManager->storeTestEmail($testEmail);
            }
            
            try {
                $result = $this->csvUploadOrchestrator->processUpload(
                    $csvFile, 
                    $testMode, 
                    $forceResend, 
                    $updatedConfig
                );
                
                $this->addFlash($result->flashType, $result->flashMessage);
                
                return $this->redirectToRoute($result->redirectRoute, $result->routeParameters);
                
            } catch (TicketMailerException $e) {
                $this->addFlash('error', $e->getUserMessage());
                
                return $this->render('csv_upload/upload.html.twig', [
                    'form' => $form->createView(),
                    'currentConfig' => $csvFieldConfig,
                ]);
            }
        }
        
        return $this->render('csv_upload/upload.html.twig', [
            'form' => $form->createView(),
            'currentConfig' => $csvFieldConfig,
        ]);
    }
      /**
     * Verarbeitet unbekannte Benutzer aus der CSV-Datei
     */
    #[Route('/unknown-users', name: 'unknown_users')]
    public function unknownUsers(Request $request): Response
    {
        $unknownUsers = $this->sessionManager->getUnknownUsers();
        
        if (empty($unknownUsers)) {
            $this->addFlash('warning', 'Keine unbekannten Benutzer zu verarbeiten');
            return $this->redirectToRoute('csv_upload');
        }
        
        if ($request->isMethod('POST')) {
            try {
                $emailMappings = $this->extractEmailMappingsFromRequest($request, $unknownUsers);
                $result = $this->csvUploadOrchestrator->processUnknownUsers($emailMappings);
                
                $this->addFlash($result->flashType, $result->message);
                
                return $this->redirectToRoute('send_emails', [
                    'testMode' => $request->query->get('testMode', 0),
                    'forceResend' => $request->query->get('forceResend', 0)
                ]);
                
            } catch (TicketMailerException $e) {
                $this->addFlash('error', $e->getUserMessage());
            }
        }
        
        return $this->render('csv_upload/unknown_users.html.twig', [
            'unknownUsers' => $unknownUsers,
        ]);
    }    /**
     * Sendet E-Mails mit Ticketinformationen an die Benutzer
     */
    #[Route('/send-emails', name: 'send_emails')]
    public function sendEmails(Request $request): Response
    {
        $testMode = (bool)$request->query->get('testMode', 0);
        $forceResend = (bool)$request->query->get('forceResend', 0);
        $testEmail = $this->sessionManager->getTestEmail();
        $ticketData = $this->sessionManager->getValidTickets();
        
        if (empty($ticketData)) {
            $this->addFlash('error', 'Keine gültigen Tickets zum Versenden gefunden');
            return $this->redirectToRoute('csv_upload');
        }
        
        try {
            $sentEmails = $this->emailService->sendTicketEmailsWithDuplicateCheck($ticketData, $testMode, $forceResend, $testEmail);
            
            $this->addFlash('success', sprintf(
                'Es wurden %d E-Mails %sversandt', 
                count($sentEmails), 
                $testMode ? 'im Testmodus ' : ''
            ));
            
            // Session-Daten nach erfolgreichem Versand löschen
            $this->sessionManager->clearUploadData();
            
        } catch (TicketMailerException $e) {
            $this->addFlash('error', $e->getUserMessage());
            $sentEmails = [];
        }
        
        return $this->render('csv_upload/send_result.html.twig', [
            'sentEmails' => $sentEmails ?? [],
            'testMode' => $testMode
        ]);
    }    /**
     * Extrahiert E-Mail-Zuordnungen aus dem Request für unbekannte Benutzer
     * 
     * @param Request $request HTTP-Request mit Form-Daten
     * @param array $unknownUsers Liste der unbekannten Benutzernamen
     * @return array Mapping von Benutzername zu E-Mail-Adresse
     */
    private function extractEmailMappingsFromRequest(Request $request, array $unknownUsers): array
    {
        $emailMappings = [];
        
        foreach ($unknownUsers as $unknownUser) {
            // Handle both old format (strings) and new format (UnknownUserWithTicket objects)
            $username = is_string($unknownUser) ? $unknownUser : $unknownUser->getUsernameString();
            
            // Benutzername für HTML-Attribut konvertieren (gleiche Logik wie im Template)
            $htmlSafeUsername = $this->convertUsernameForHtmlAttribute($username);
            $emailInput = $request->request->get('email_' . $htmlSafeUsername);
            
            if (!empty($emailInput)) {
                try {
                    // E-Mail normalisieren (Outlook-Format -> Standard-Format)
                    $normalizedEmail = $this->emailNormalizer->normalizeEmail($emailInput);
                    $emailMappings[$username] = $normalizedEmail;
                } catch (\InvalidArgumentException $e) {
                    // Fehler wird im Frontend durch JavaScript abgefangen
                    // Falls JavaScript deaktiviert ist, wird hier ein Fallback bereitgestellt
                    throw new TicketMailerException(
                        "Ungültige E-Mail-Adresse für Benutzer '{$username}': " . $e->getMessage(),
                        'validation_error'
                    );
                }
            }
        }
        
        return $emailMappings;
    }

    /**
     * Konvertiert einen Benutzernamen für die Verwendung als HTML-Attribut
     * Repliziert die Logik von Twig's html_attr Escaping für Konsistenz mit dem Template
     * 
     * @param string $username Der ursprüngliche Benutzername
     * @return string Der für HTML-Attribute sichere Benutzername
     */
    private function convertUsernameForHtmlAttribute(string $username): string
    {
        // Grundlegende HTML-Attribut-Escaping-Logik
        // Punkte werden zu Unterstrichen, da sie in HTML-Attributnamen problematisch sind
        return str_replace('.', '_', $username);
    }

}
