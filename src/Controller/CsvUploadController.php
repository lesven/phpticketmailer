<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\EmailSent;
use App\Entity\CsvFieldConfig;
use App\Form\CsvUploadType;
use App\Service\CsvProcessor;
use App\Service\EmailService;
use App\Repository\UserRepository;
use App\Repository\CsvFieldConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
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
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly CsvFieldConfigRepository $csvFieldConfigRepository,
        private readonly CsvProcessor $csvProcessor,
        private readonly EmailService $emailService,
        private readonly RequestStack $requestStack
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
        $form->handleRequest($request);
          if ($form->isSubmitted() && $form->isValid()) {
            $csvFile = $form->get('csvFile')->getData();
            $testMode = $form->get('testMode')->getData();
            $forceResend = $form->get('forceResend')->getData();
            $updatedConfig = $form->get('csvFieldConfig')->getData();
            
            // CSV-Konfiguration speichern
            $this->csvFieldConfigRepository->saveConfig($updatedConfig);
            
            $result = $this->processCsvFile($csvFile, $testMode, $forceResend, $updatedConfig);
            
            return $result;
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
        $unknownUsers = $this->getUnknownUsersFromSession();
        
        if (empty($unknownUsers)) {
            $this->addFlash('warning', 'Keine unbekannten Benutzer zu verarbeiten');
            return $this->redirectToRoute('csv_upload');
        }
          if ($request->isMethod('POST')) {
            $this->processUnknownUsers($request, $unknownUsers);
            
            $this->addFlash('success', 'Neue Benutzer wurden erfolgreich angelegt');
            return $this->redirectToRoute('send_emails', [
                'testMode' => $request->query->get('testMode', 0),
                'forceResend' => $request->query->get('forceResend', 0)
            ]);
        }
        
        return $this->render('csv_upload/unknown_users.html.twig', [
            'unknownUsers' => $unknownUsers,
        ]);
    }
      /**
     * Sendet E-Mails mit Ticketinformationen an die Benutzer
     */
    #[Route('/send-emails', name: 'send_emails')]
    public function sendEmails(Request $request): Response
    {
        $testMode = (bool)$request->query->get('testMode', 0);
        $forceResend = (bool)$request->query->get('forceResend', 0);
        $ticketData = $this->getValidTicketsFromSession();
        
        if (empty($ticketData)) {
            $this->addFlash('error', 'Keine gültigen Tickets zum Versenden gefunden');
            return $this->redirectToRoute('csv_upload');
        }
        
        $sentEmails = $this->emailService->sendTicketEmailsWithDuplicateCheck($ticketData, $testMode, $forceResend);
        
        $this->addFlash('success', sprintf(
            'Es wurden %d E-Mails %sversandt', 
            count($sentEmails), 
            $testMode ? 'im Testmodus ' : ''
        ));
        
        return $this->render('csv_upload/send_result.html.twig', [
            'sentEmails' => $sentEmails,
            'testMode' => $testMode
        ]);
    }    /**
     * Verarbeitet die CSV-Datei und leitet entsprechend weiter
     */
    private function processCsvFile(mixed $csvFile, bool $testMode, bool $forceResend, CsvFieldConfig $csvFieldConfig): Response
    {
        $result = $this->csvProcessor->process($csvFile, $csvFieldConfig);
        
        $session = $this->requestStack->getSession();
        $session->set('unknown_users', $result['unknownUsers'] ?? []);
        $session->set('valid_tickets', $result['validTickets'] ?? []);
        
        if (!empty($result['unknownUsers'])) {
            $this->addFlash('info', sprintf(
                'Es wurden %d unbekannte Benutzer gefunden', 
                count($result['unknownUsers'])
            ));
            return $this->redirectToRoute('unknown_users', [
                'testMode' => $testMode ? 1 : 0,
                'forceResend' => $forceResend ? 1 : 0
            ]);
        }
        
        $this->addFlash('success', 'CSV-Datei erfolgreich verarbeitet');
        return $this->redirectToRoute('send_emails', [
            'testMode' => $testMode ? 1 : 0,
            'forceResend' => $forceResend ? 1 : 0
        ]);
    }
    
    /**
     * Erstellt neue Benutzer aus unbekannten Benutzernamen und E-Mails
     */
    private function processUnknownUsers(Request $request, array $unknownUsers): void
    {
        $newUsers = [];
        
        foreach ($unknownUsers as $username) {
            $email = $request->request->get('email_' . $username);
            if (!$email) {
                continue;
            }
            
            $user = new User();
            $user->setUsername($username);
            $user->setEmail($email);
            
            $this->entityManager->persist($user);
            $newUsers[] = $user;
        }
        
        if (!empty($newUsers)) {
            $this->entityManager->flush();
        }
    }
    
    /**
     * Holt unbekannte Benutzer aus der Session
     */
    private function getUnknownUsersFromSession(): array
    {
        return $this->requestStack->getSession()->get('unknown_users', []);
    }
    
    /**
     * Holt die gültigen Tickets aus der Session
     */
    private function getValidTicketsFromSession(): array
    {
        return $this->requestStack->getSession()->get('valid_tickets', []);
    }
}