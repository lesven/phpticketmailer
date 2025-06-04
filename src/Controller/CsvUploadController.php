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
use App\Exception\TicketMailerException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
        private readonly CsvFieldConfigRepository $csvFieldConfigRepository
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
        $form->handleRequest($request);        if ($form->isSubmitted() && $form->isValid()) {
            $csvFile = $form->get('csvFile')->getData();
            $testMode = $form->get('testMode')->getData();
            $forceResend = $form->get('forceResend')->getData();
            $updatedConfig = $form->get('csvFieldConfig')->getData();
            
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
        $ticketData = $this->sessionManager->getValidTickets();
        
        if (empty($ticketData)) {
            $this->addFlash('error', 'Keine gültigen Tickets zum Versenden gefunden');
            return $this->redirectToRoute('csv_upload');
        }
        
        try {
            $sentEmails = $this->emailService->sendTicketEmailsWithDuplicateCheck($ticketData, $testMode, $forceResend);
            
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
        
        foreach ($unknownUsers as $username) {
            $email = $request->request->get('email_' . $username);
            if (!empty($email)) {
                $emailMappings[$username] = $email;
            }
        }
        
        return $emailMappings;
    }

}
