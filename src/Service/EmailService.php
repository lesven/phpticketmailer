<?php

namespace App\Service;

use App\Entity\EmailSent;
use App\Entity\SMTPConfig;
use App\Repository\SMTPConfigRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

class EmailService
{
    /**
     * The mailer service for sending emails
     */
    private $mailer;
    private $entityManager;
    private $userRepository;
    private $smtpConfigRepository;
    private $params;
    private $projectDir;
    
    public function __construct(
        MailerInterface $mailer,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        SMTPConfigRepository $smtpConfigRepository,
        ParameterBagInterface $params,
        string $projectDir
    ) {
        $this->mailer = $mailer;
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;
        $this->smtpConfigRepository = $smtpConfigRepository;
        $this->params = $params;
        $this->projectDir = $projectDir;
    }
    
    public function sendTicketEmails(array $ticketData, bool $testMode = false): array
    {
        $sentEmails = [];
        $templateContent = $this->getEmailTemplate();
        $testEmailAddress = $this->params->get('app.test_email', 'test@example.com');
        
        // Versuche, die SMTP-Konfiguration aus der Datenbank zu laden
        $config = $this->smtpConfigRepository->getConfig();
        
        // Wenn keine Konfiguration vorhanden ist, verwende die Standard-Parameter
        if ($config) {
            $senderEmail = $config->getSenderEmail();
            $senderName = $config->getSenderName();
        } else {
            $senderEmail = $this->params->get('app.sender_email', 'noreply@example.com');
            $senderName = $this->params->get('app.sender_name', 'Ticket-System');
        }
        
        $defaultSubject = $this->params->get('app.email_subject', 'Ihre Rückmeldung zu Ticket {{ticketId}}');
        $ticketBaseUrl = $this->params->get('app.ticket_base_url', 'https://www.ticket.de');
        
        // Use current time for all email records
        $currentTime = new \DateTime();
        
        foreach ($ticketData as $ticket) {
            $user = $this->userRepository->findByUsername($ticket['username']);
            
            if (!$user) {
                // Keine E-Mail-Adresse für diesen Benutzer gefunden
                $emailSent = new EmailSent();
                $emailSent->setTicketId($ticket['ticketId']);
                $emailSent->setUsername($ticket['username']);
                $emailSent->setEmail('');
                $emailSent->setSubject('');
                $emailSent->setStatus('error: no email found');
                $emailSent->setTimestamp(clone $currentTime);
                $emailSent->setTestMode($testMode);
                $emailSent->setTicketName($ticket['ticketName']);
                
                $this->entityManager->persist($emailSent);
                $sentEmails[] = $emailSent;
                continue;
            }
            
            $recipientEmail = $testMode ? $testEmailAddress : $user->getEmail();
            $subject = str_replace('{{ticketId}}', $ticket['ticketId'], $defaultSubject);
            
            // Template-Platzhalter ersetzen
            $ticketLink = rtrim($ticketBaseUrl, '/') . '/' . $ticket['ticketId'];
            $emailBody = $templateContent;
            $emailBody = str_replace('{{ticketId}}', $ticket['ticketId'], $emailBody);
            $emailBody = str_replace('{{ticketLink}}', $ticketLink, $emailBody);
            $emailBody = str_replace('{{ticketName}}', $ticket['ticketName'] ?? '', $emailBody);
            $emailBody = str_replace('{{username}}', $ticket['username'], $emailBody);
            
            // Hinweis im Testmodus hinzufügen
            if ($testMode) {
                $emailBody = "*** TESTMODUS - E-Mail wäre an {$user->getEmail()} gegangen ***\n\n" . $emailBody;
            }
            
            // E-Mail erstellen und versenden
            $email = (new Email())
                ->from(new Address($senderEmail, $senderName))
                ->to($recipientEmail)
                ->subject($subject)
                ->text($emailBody);
                
            try {
                // Wenn eine SMTP-Konfiguration vorhanden ist, verwende sie
                if ($config) {
                    $transport = Transport::fromDsn($config->getDSN());
                    $transport->send($email);
                } else {
                    $this->mailer->send($email);
                }
                $status = 'sent';
            } catch (\Exception $e) {
                $status = 'error: ' . substr($e->getMessage(), 0, 200);
            }
            
            // Versand in der Datenbank protokollieren
            $emailSent = new EmailSent();
            $emailSent->setTicketId($ticket['ticketId']);
            $emailSent->setUsername($ticket['username']);
            $emailSent->setEmail($testMode ? $testEmailAddress : $user->getEmail());
            $emailSent->setSubject($subject);
            $emailSent->setStatus($status);
            $emailSent->setTimestamp(clone $currentTime);
            $emailSent->setTestMode($testMode);
            $emailSent->setTicketName($ticket['ticketName']);
            
            $this->entityManager->persist($emailSent);
            $sentEmails[] = $emailSent;
        }
        
        try {
            $this->entityManager->flush();
        } catch (\Exception $e) {
            // Log error for debugging
            error_log('Error saving email records: ' . $e->getMessage());
        }
        
        return $sentEmails;
    }
    
    private function getEmailTemplate(): string
    {
        $templatePath = $this->projectDir . '/templates/emails/email_template.txt';
        
        if (!file_exists($templatePath)) {
            // Standardtemplate zurückgeben, wenn keine Datei vorhanden ist
            return "Sehr geehrter Kunde,\n\n" .
                   "wir bitten Sie um eine Rückmeldung zu Ihrem Ticket {{ticketId}}:\n" .
                   "{{ticketName}}\n\n" .
                   "Sie können Ihr Ticket über diesen Link aufrufen:\n" .
                   "{{ticketLink}}\n\n" .
                   "Mit freundlichen Grüßen,\n" .
                   "Ihr Support-Team";
        }
        
        return file_get_contents($templatePath);
    }
}