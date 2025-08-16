<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\EmailSent;
use App\Form\CsvUploadType;
use App\Service\CsvProcessor;
use App\Service\EmailService;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller für das Hochladen und Verarbeiten von CSV-Dateien mit Ticketdaten
 * 
 * Dieser Controller verwaltet den gesamten Prozess vom Hochladen der CSV-Datei,
 * über die Bearbeitung unbekannter Benutzer bis zum Versand der Ticket-E-Mails.
 */
class CsvUploadController extends AbstractController
{
    /**
     * Doctrine Entity Manager für Datenbankoperationen
     * 
     * @var EntityManagerInterface
     */
    private $entityManager;
    
    /**
     * Repository für User-Entitäten
     * 
     * @var UserRepository
     */
    private $userRepository;
    
    /**
     * Service zur Verarbeitung von CSV-Dateien
     * 
     * @var CsvProcessor
     */
    private $csvProcessor;
    
    /**
     * Service zum Versenden von E-Mails
     * 
     * @var EmailService
     */
    private $emailService;
    
    /**
     * Konstruktor zum Injection der benötigten Abhängigkeiten
     * 
     * @param EntityManagerInterface $entityManager Doctrine Entity Manager
     * @param UserRepository $userRepository Repository für User-Entitäten
     * @param CsvProcessor $csvProcessor Service zur CSV-Verarbeitung
     * @param EmailService $emailService Service zum E-Mail-Versand
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        CsvProcessor $csvProcessor,
        EmailService $emailService
    ) {
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;
        $this->csvProcessor = $csvProcessor;
        $this->emailService = $emailService;
    }
    
    /**
     * Zeigt das Formular zum Hochladen einer CSV-Datei an und verarbeitet die Übermittlung
     * 
     [Route("/upload", name="csv_upload")]
     * @param Request $request HTTP-Anfrage
     * @return Response HTTP-Antwort
     */
    public function upload(Request $request): Response
    {
        $form = $this->createForm(CsvUploadType::class);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $csvFile = $form->get('csvFile')->getData();
            $testMode = $form->get('testMode')->getData();
            
            // CSV-Datei verarbeiten
            $result = $this->csvProcessor->process($csvFile);
            
            // Unbekannte Benutzer speichern für die Nachbearbeitung
            $session = $request->getSession();
            $session->set('unknown_users', $result['unknownUsers']);
            
            // Wenn es unbekannte Benutzer gibt, zur Eingabeseite weiterleiten
            if (!empty($result['unknownUsers'])) {
                return $this->redirectToRoute('unknown_users');
            }
            
            // Wenn alle Benutzer bekannt sind, direkt E-Mails versenden
            return $this->redirectToRoute('send_emails', [
                'testMode' => $testMode ? 1 : 0
            ]);
        }
        
        return $this->render('csv_upload/upload.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    
    /**
     * Verarbeitet unbekannte Benutzer aus der CSV-Datei
     * 
     * Zeigt eine Seite an, auf der E-Mail-Adressen für unbekannte Benutzernamen eingegeben werden können.
     * Die eingegebenen E-Mail-Adressen werden neuen Benutzern zugeordnet und in der Datenbank gespeichert.
     * 
     [Route("/unknown-users", name="unknown_users")]
     * @param Request $request HTTP-Anfrage
     * @return Response HTTP-Antwort
     */
    public function unknownUsers(Request $request): Response
    {
        $session = $request->getSession();
        $unknownUsers = $session->get('unknown_users', []);
        
        if (empty($unknownUsers)) {
            return $this->redirectToRoute('csv_upload');
        }
        
        if ($request->isMethod('POST')) {
            foreach ($unknownUsers as $username) {
                $email = $request->request->get('email_' . $username);
                if ($email) {
                    $user = new User();
                    $user->setUsername($username);
                    $user->setEmail($email);
                    
                    $this->entityManager->persist($user);
                }
            }
            
            $this->entityManager->flush();
            
            // Nach dem Speichern zur E-Mail-Versandseite weiterleiten
            return $this->redirectToRoute('send_emails', [
                'testMode' => $request->query->get('testMode', 0)
            ]);
        }
        
        return $this->render('csv_upload/unknown_users.html.twig', [
            'unknownUsers' => $unknownUsers,
        ]);
    }
    
    /**
     * Sendet E-Mails mit Ticketinformationen an die Benutzer
     * 
     * Verwendet die in der Session gespeicherten Ticketdaten, um E-Mails zu versenden.
     * Unterstützt einen Testmodus, in dem E-Mails nicht tatsächlich versendet werden.
     * 
     [Route("/send-emails", name="send_emails")]
     * @param Request $request HTTP-Anfrage
     * @return Response HTTP-Antwort
     */
    public function sendEmails(Request $request): Response
    {
        $testMode = (bool)$request->query->get('testMode', 0);
        $session = $request->getSession();
        $ticketData = $session->get('valid_tickets', []);
        
        if (empty($ticketData)) {
            $this->addFlash('error', 'Keine gültigen Tickets zum Versenden gefunden');
            return $this->redirectToRoute('csv_upload');
        }
        
        $sentEmails = $this->emailService->sendTicketEmails($ticketData, $testMode);
        
        return $this->render('csv_upload/send_result.html.twig', [
            'sentEmails' => $sentEmails,
            'testMode' => $testMode
        ]);
    }
}