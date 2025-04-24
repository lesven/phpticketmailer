<?php
/**
 * EmailService.php
 * 
 * Diese Klasse ist verantwortlich für das Versenden von E-Mails an Benutzer
 * mit Informationen zu ihren Tickets. Sie kann sowohl im normalen als auch im
 * Testmodus arbeiten und speichert alle Sendeversuche in der Datenbank.
 * 
 * @package App\Service
 */

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
     * Die Mailer-Komponente zum Versenden von E-Mails
     * @var MailerInterface
     */
    private $mailer;
    
    /**
     * Der Entity Manager zur Datenbankinteraktion
     * @var EntityManagerInterface
     */
    private $entityManager;
    
    /**
     * Das User Repository zum Abrufen von Benutzerinformationen
     * @var UserRepository
     */
    private $userRepository;
    
    /**
     * Das SMTP-Konfigurations-Repository zum Abrufen von SMTP-Einstellungen
     * @var SMTPConfigRepository
     */
    private $smtpConfigRepository;
    
    /**
     * Der Parameter-Bag für Zugriff auf Konfigurationswerte
     * @var ParameterBagInterface
     */
    private $params;
    
    /**
     * Der Pfad zum Projektverzeichnis
     * @var string
     */
    private $projectDir;
    
    /**
     * Konstruktor mit Dependency Injection aller benötigten Services
     * 
     * @param MailerInterface $mailer Der Symfony Mailer Service
     * @param EntityManagerInterface $entityManager Der Doctrine Entity Manager
     * @param UserRepository $userRepository Das User Repository
     * @param SMTPConfigRepository $smtpConfigRepository Das SMTP Konfigurations-Repository
     * @param ParameterBagInterface $params Der Parameter-Bag für Konfigurationswerte
     * @param string $projectDir Der Pfad zum Projektverzeichnis
     */
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
    
    /**
     * Sendet E-Mails für alle übergebenen Ticket-Datensätze
     * 
     * Diese Methode iteriert über alle Ticket-Datensätze und sendet
     * für jeden eine E-Mail an den zugehörigen Benutzer. Alle E-Mail-Sendungen
     * werden protokolliert und in der Datenbank gespeichert.
     * 
     * @param array $ticketData Array mit Ticket-Daten (ticketId, username, ticketName)
     * @param bool $testMode Gibt an, ob die E-Mails im Testmodus gesendet werden sollen
     * @return array Array mit allen erstellten EmailSent-Entitäten
     */
    public function sendTicketEmails(array $ticketData, bool $testMode = false): array
    {
        $sentEmails = [];
        $currentTime = new \DateTime();
        $emailConfig = $this->getEmailConfiguration();
        $templateContent = $this->getEmailTemplate();
        
        foreach ($ticketData as $ticket) {
            $emailRecord = $this->processTicketEmail(
                $ticket, 
                $emailConfig, 
                $templateContent, 
                $testMode, 
                $currentTime
            );
            
            $this->entityManager->persist($emailRecord);
            $sentEmails[] = $emailRecord;
        }
        
        try {
            $this->entityManager->flush();
        } catch (\Exception $e) {
            error_log('Error saving email records: ' . $e->getMessage());
        }
        
        return $sentEmails;
    }
    
    /**
     * Verarbeitet einen einzelnen Ticket-Datensatz und versendet die E-Mail
     * 
     * Diese Methode findet den Benutzer, erstellt die E-Mail, sendet sie
     * und protokolliert den Versand. Bei Fehlern wird der Fehlerstatus
     * in der protokollierten Entität gespeichert.
     * 
     * @param array $ticket Der Ticket-Datensatz (ticketId, username, ticketName)
     * @param array $emailConfig Die E-Mail-Konfiguration
     * @param string $templateContent Der Inhalt der E-Mail-Vorlage
     * @param bool $testMode Gibt an, ob im Testmodus gesendet wird
     * @param \DateTime $timestamp Der Zeitstempel der Sendung
     * @return EmailSent Die erstellte EmailSent-Entität
     */
    private function processTicketEmail(
        array $ticket,
        array $emailConfig,
        string $templateContent,
        bool $testMode,
        \DateTime $timestamp
    ): EmailSent {
        $user = $this->userRepository->findByUsername($ticket['username']);
        
        // Erstelle das E-Mail-Protokoll
        $emailRecord = new EmailSent();
        $emailRecord->setTicketId($ticket['ticketId']);
        $emailRecord->setUsername($ticket['username']);
        $emailRecord->setTimestamp(clone $timestamp);
        $emailRecord->setTestMode($testMode);
        $emailRecord->setTicketName($ticket['ticketName'] ?? '');
        
        // Wenn kein Benutzer gefunden wurde
        if (!$user) {
            $emailRecord->setEmail('');
            $emailRecord->setSubject('');
            $emailRecord->setStatus('error: no email found');
            return $emailRecord;
        }
        
        // E-Mail-Einstellungen
        $recipientEmail = $testMode ? $emailConfig['testEmail'] : $user->getEmail();
        $subject = str_replace('{{ticketId}}', $ticket['ticketId'], $emailConfig['subject']);
        $emailRecord->setEmail($recipientEmail);
        $emailRecord->setSubject($subject);
        
        // E-Mail-Inhalt vorbereiten
        $emailBody = $this->prepareEmailContent(
            $templateContent,
            $ticket, 
            $user,
            $emailConfig['ticketBaseUrl'],
            $testMode
        );
        
        // E-Mail senden
        try {
            $this->sendEmail(
                $recipientEmail,
                $subject,
                $emailBody,
                $emailConfig
            );
            $emailRecord->setStatus('sent');
        } catch (\Exception $e) {
            $emailRecord->setStatus('error: ' . substr($e->getMessage(), 0, 200));
        }
        
        return $emailRecord;
    }
    
    /**
     * Bereitet den E-Mail-Inhalt vor und ersetzt alle Platzhalter
     * 
     * Ersetzt alle Platzhalter wie {{ticketId}}, {{ticketLink}}, etc.
     * durch die tatsächlichen Werte aus dem Ticket-Datensatz.
     * Im Testmodus wird außerdem ein Hinweis an den Anfang der E-Mail gesetzt.
     * 
     * @param string $template Die E-Mail-Vorlage
     * @param array $ticket Der Ticket-Datensatz
     * @param mixed $user Der Benutzer-Datensatz
     * @param string $ticketBaseUrl Die Basis-URL für Ticket-Links
     * @param bool $testMode Gibt an, ob im Testmodus gesendet wird
     * @return string Der vorbereitete E-Mail-Inhalt
     */
    private function prepareEmailContent(
        string $template,
        array $ticket,
        $user,
        string $ticketBaseUrl,
        bool $testMode
    ): string {
        $ticketLink = rtrim($ticketBaseUrl, '/') . '/' . $ticket['ticketId'];
        $emailBody = $template;
        $emailBody = str_replace('{{ticketId}}', $ticket['ticketId'], $emailBody);
        $emailBody = str_replace('{{ticketLink}}', $ticketLink, $emailBody);
        $emailBody = str_replace('{{ticketName}}', $ticket['ticketName'] ?? '', $emailBody);
        $emailBody = str_replace('{{username}}', $ticket['username'], $emailBody);
        
        // Hinweis im Testmodus hinzufügen
        if ($testMode) {
            $emailBody = "*** TESTMODUS - E-Mail wäre an {$user->getEmail()} gegangen ***\n\n" . $emailBody;
        }
        
        return $emailBody;
    }
    
    /**
     * Sendet die E-Mail über den konfigurierten Transport
     * 
     * Diese Methode verwendet entweder die konfigurierte SMTP-Verbindung
     * oder den Standard-Mailer von Symfony, um die E-Mail zu versenden.
     * 
     * @param string $recipient Die E-Mail-Adresse des Empfängers
     * @param string $subject Der Betreff der E-Mail
     * @param string $content Der Inhalt der E-Mail
     * @param array $config Die E-Mail-Konfiguration
     */
    private function sendEmail(
        string $recipient,
        string $subject,
        string $content,
        array $config
    ): void {
        $email = (new Email())
            ->from(new Address($config['senderEmail'], $config['senderName']))
            ->to($recipient)
            ->subject($subject)
            ->text($content);
            
        // Wenn eine SMTP-Konfiguration vorhanden ist, verwende sie
        if ($config['useCustomSMTP']) {
            $transport = Transport::fromDsn($config['smtpDSN']);
            $transport->send($email);
        } else {
            $this->mailer->send($email);
        }
    }
    
    /**
     * Lädt die E-Mail-Konfiguration aus der Datenbank oder den Parametern
     * 
     * Diese Methode versucht, eine SMTP-Konfiguration aus der Datenbank zu laden.
     * Wenn keine vorhanden ist, werden die Standard-Parameter aus der Konfiguration verwendet.
     * 
     * @return array Die E-Mail-Konfiguration als assoziatives Array
     */
    private function getEmailConfiguration(): array
    {
        $config = $this->smtpConfigRepository->getConfig();
        
        $emailConfig = [
            'subject' => $this->params->get('app.email_subject', 'Ihre Rückmeldung zu Ticket {{ticketId}}'),
            'ticketBaseUrl' => $this->params->get('app.ticket_base_url', 'https://www.ticket.de'),
            'testEmail' => $this->params->get('app.test_email', 'test@example.com'),
            'useCustomSMTP' => false,
        ];
        
        // Wenn eine Konfiguration vorhanden ist, verwende sie
        if ($config) {
            $emailConfig['senderEmail'] = $config->getSenderEmail();
            $emailConfig['senderName'] = $config->getSenderName();
            $emailConfig['useCustomSMTP'] = true;
            $emailConfig['smtpDSN'] = $config->getDSN();
        } else {
            $emailConfig['senderEmail'] = $this->params->get('app.sender_email', 'noreply@example.com');
            $emailConfig['senderName'] = $this->params->get('app.sender_name', 'Ticket-System');
        }
        
        return $emailConfig;
    }
    
    /**
     * Lädt die E-Mail-Vorlage aus der Datei oder erstellt eine Standard-Vorlage
     * 
     * Diese Methode versucht, die E-Mail-Vorlage aus einer Datei zu laden.
     * Wenn die Datei nicht existiert, wird eine Standard-Vorlage zurückgegeben.
     * 
     * @return string Die E-Mail-Vorlage
     */
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