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
use App\ValueObject\EmailStatus;
use App\ValueObject\TicketData;
use App\ValueObject\TicketId;
use App\Repository\SMTPConfigRepository;
use App\Repository\UserRepository;
use App\Repository\EmailSentRepository;
use App\Event\Email\EmailSentEvent;
use App\Event\Email\EmailFailedEvent;
use App\Event\Email\EmailSkippedEvent;
use App\Event\Email\BulkEmailCompletedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use App\ValueObject\EmailAddress;

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
     * Das EmailSent Repository zum Prüfen von bereits versendeten E-Mails
     * @var EmailSentRepository
     */
    private $emailSentRepository;
    
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
     * @param EmailSentRepository $emailSentRepository Das EmailSent Repository
     * @param ParameterBagInterface $params Der Parameter-Bag für Konfigurationswerte
     * @param string $projectDir Der Pfad zum Projektverzeichnis
     */
    public function __construct(
        MailerInterface $mailer,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        SMTPConfigRepository $smtpConfigRepository,
        EmailSentRepository $emailSentRepository,
        ParameterBagInterface $params,
        string $projectDir,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        $this->mailer = $mailer;
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;
        $this->smtpConfigRepository = $smtpConfigRepository;
        $this->emailSentRepository = $emailSentRepository;
        $this->params = $params;
        $this->projectDir = $projectDir;
    }
    
    /**
     * Sendet E-Mails für alle übergebenen Ticket-Datensätze
     *
     * @param TicketData[] $ticketData Liste der zu verarbeitenden Tickets
     * @param bool $testMode Gibt an, ob die E-Mails im Testmodus gesendet werden sollen
     * @param string|null $customTestEmail Optionale Test-E-Mail-Adresse für den Testmodus
     * @return array Array mit allen erstellten EmailSent-Entitäten
     */
    public function sendTicketEmails(array $ticketData, bool $testMode = false, ?string $customTestEmail = null): array
    {
        return $this->sendTicketEmailsWithDuplicateCheck($ticketData, $testMode, true, $customTestEmail);
    }

    /**
     * Sendet E-Mails für alle übergebenen Ticket-Datensätze mit Duplikatsprüfung
     * 
     * Diese Methode iteriert über alle Ticket-Datensätze und sendet
     * für jeden eine E-Mail an den zugehörigen Benutzer. Optional wird geprüft,
     * ob für eine Ticket-ID bereits eine E-Mail gesendet wurde.
     *
     * @param TicketData[] $ticketData Liste der Ticket-Daten
     * @param bool $testMode Gibt an, ob die E-Mails im Testmodus gesendet werden sollen
     * @param bool $forceResend Gibt an, ob bereits verarbeitete Tickets erneut versendet werden sollen
     * @param string|null $customTestEmail Optionale Test-E-Mail-Adresse für den Testmodus
     * @return array Array mit allen erstellten EmailSent-Entitäten
     */
    public function sendTicketEmailsWithDuplicateCheck(array $ticketData, bool $testMode = false, bool $forceResend = false, ?string $customTestEmail = null): array
    {
        $startTime = microtime(true);
        $sentEmails = [];
        $processedTicketIds = []; // Für innerhalb der CSV-Datei
        $currentTime = new \DateTime();
        $emailConfig = $this->getEmailConfiguration();
        
        // Custom test email verwenden, wenn im Testmodus bereitgestellt
        if ($testMode && $customTestEmail !== null && !empty(trim($customTestEmail))) {
            $emailConfig['testEmail'] = trim($customTestEmail);
        }
        
        $templateContent = $this->getEmailTemplate();

        // Prüfe bereits verarbeitete Tickets in der Datenbank, wenn forceResend deaktiviert ist
        $existingTickets = [];
        if (!$forceResend) {
            $ticketIds = array_map(fn(TicketData $ticket) => (string) $ticket->ticketId, $ticketData);
            $existingTickets = $this->emailSentRepository->findExistingTickets($ticketIds);
        }
        
        foreach ($ticketData as $ticket) {
            $ticketId = $ticket->ticketId;
            
            // Prüfe auf Duplikate innerhalb der aktuellen CSV-Datei
            if (isset($processedTicketIds[(string) $ticketId])) {
                $emailRecord = $this->createSkippedEmailRecord(
                    $ticket,
                    $currentTime,
                    $testMode,
                    EmailStatus::duplicateInCsv()
                );
                try {
                    $this->entityManager->persist($emailRecord);
                    $this->entityManager->flush();
                    $sentEmails[] = $emailRecord;
                    
                    // 🔥 EVENT: E-Mail übersprungen (Duplikat in CSV)
                    $this->eventDispatcher->dispatch(new EmailSkippedEvent(
                        $emailRecord->getTicketId(),
                        $emailRecord->getUsername(),
                        $emailRecord->getEmail(),
                        $emailRecord->getStatus(),
                        $emailRecord->getTestMode(),
                        $emailRecord->getTicketName()
                    ));
                } catch (\Exception $e) {
                    error_log('Error saving duplicate record for ticket ' . $ticketId . ': ' . $e->getMessage());
                }
                continue;
            }
            
            // Prüfe auf bereits verarbeitete Tickets in der Datenbank
            if (!$forceResend && isset($existingTickets[(string) $ticketId])) {
                $existingEmail = $existingTickets[(string) $ticketId];
                $emailRecord = $this->createSkippedEmailRecord(
                    $ticket,
                    $currentTime,
                    $testMode,
                    EmailStatus::alreadyProcessed($existingEmail->getTimestamp())
                );
                try {
                    $this->entityManager->persist($emailRecord);
                    $this->entityManager->flush();
                    $sentEmails[] = $emailRecord;
                    
                    // 🔥 EVENT: E-Mail übersprungen (bereits verarbeitet)
                    $this->eventDispatcher->dispatch(new EmailSkippedEvent(
                        $emailRecord->getTicketId(),
                        $emailRecord->getUsername(),
                        $emailRecord->getEmail(),
                        $emailRecord->getStatus(),
                        $emailRecord->getTestMode(),
                        $emailRecord->getTicketName()
                    ));
                } catch (\Exception $e) {
                    error_log('Error saving existing ticket record for ticket ' . $ticketId . ': ' . $e->getMessage());
                }
                $processedTicketIds[(string) $ticketId] = true;
                continue;
            }

            // Prüfe, ob der Benutzer von Umfragen ausgeschlossen ist
            $user = $this->userRepository->findByUsername((string) $ticket->username);
            if ($user && $user->isExcludedFromSurveys()) {
                $emailRecord = $this->createSkippedEmailRecord(
                    $ticket,
                    $currentTime,
                    $testMode,
                    EmailStatus::excludedFromSurvey()
                );
                try {
                    $this->entityManager->persist($emailRecord);
                    $this->entityManager->flush();
                    $sentEmails[] = $emailRecord;
                    
                    // 🔥 EVENT: E-Mail übersprungen (Benutzer ausgeschlossen)
                    $this->eventDispatcher->dispatch(new EmailSkippedEvent(
                        $emailRecord->getTicketId(),
                        $emailRecord->getUsername(),
                        $emailRecord->getEmail(),
                        $emailRecord->getStatus(),
                        $emailRecord->getTestMode(),
                        $emailRecord->getTicketName()
                    ));
                } catch (\Exception $e) {
                    error_log('Error saving excluded user record for ticket ' . $ticketId . ': ' . $e->getMessage());
                }
                $processedTicketIds[(string) $ticketId] = true;
                continue;
            }
            
            // Normaler E-Mail-Versand (User existiert und ist nicht ausgeschlossen)
            $emailRecord = $this->processTicketEmail(
                $ticket, 
                $emailConfig, 
                $templateContent, 
                $testMode, 
                $currentTime
            );
            
            // Speichere jeden Datensatz einzeln, um Batch-Fehler zu vermeiden
            try {
                $this->entityManager->persist($emailRecord);
                $this->entityManager->flush();
                $sentEmails[] = $emailRecord;
            } catch (\Exception $e) {
                error_log('Error saving email record for ticket ' . (string) $ticket->ticketId . ': ' . $e->getMessage());
                // Erstelle einen Fehler-Datensatz stattdessen
                $errorRecord = new EmailSent();
                // Copy relevant fields from $emailRecord to $errorRecord
                $errorRecord->setTicketId($emailRecord->getTicketId());
                $errorRecord->setUsername($emailRecord->getUsername());
                $errorRecord->setTimestamp($emailRecord->getTimestamp());
                $errorRecord->setTestMode($emailRecord->getTestMode());
                $errorRecord->setStatus(EmailStatus::error('database save failed - ' . $e->getMessage()));
                $errorRecord->setEmail($emailRecord->getEmail());
                $errorRecord->setSubject($emailRecord->getSubject());
                $errorRecord->setTicketName($emailRecord->getTicketName());
                // Add any other fields that need to be copied here
                try {
                    $this->entityManager->persist($errorRecord);
                    $this->entityManager->flush();
                    $sentEmails[] = $errorRecord;
                } catch (\Exception $innerE) {
                    error_log('Critical: Could not save error record: ' . $innerE->getMessage());
                }
            }
            $processedTicketIds[(string) $ticketId] = true;
        }

        // Cleanup: Alle verbleibenden Datensätze (falls welche übrig sind)
        try {
            $this->entityManager->flush();
        } catch (\Exception $e) {
            error_log('Error saving remaining email records: ' . $e->getMessage());
        }

        // 🔥 EVENT: Bulk E-Mail-Versand abgeschlossen
        $endTime = microtime(true);
        $sentCount = count(array_filter($sentEmails, fn($email) => $email->getStatus()?->isSent()));
        $failedCount = count(array_filter($sentEmails, fn($email) => $email->getStatus()?->isError()));
        $skippedCount = count($sentEmails) - $sentCount - $failedCount;

        $this->eventDispatcher->dispatch(new BulkEmailCompletedEvent(
            count($ticketData),
            $sentCount,
            $failedCount,
            $skippedCount,
            $testMode,
            $endTime - $startTime
        ));

        return $sentEmails;
    }

    /**
     * Erstellt ein EmailSent-Record für übersprungene Tickets
     */
    private function createSkippedEmailRecord(TicketData $ticket, \DateTime $timestamp, bool $testMode, EmailStatus|string $status): EmailSent
    {
        $user = $this->userRepository->findByUsername((string) $ticket->username);

        $emailRecord = new EmailSent();
        $emailRecord->setTicketId($ticket->ticketId);
        $emailRecord->setUsername((string) $ticket->username);
        // Use EmailAddress VO if available, otherwise null
        $emailRecord->setEmail($user ? $user->getEmail() : null);
        $emailRecord->setSubject('');
        $emailRecord->setStatus($status);
        $emailRecord->setTimestamp(clone $timestamp);
        $emailRecord->setTestMode($testMode);
        $emailRecord->setTicketName($ticket->ticketName);

        return $emailRecord;
    }
    
    /**
     * Verarbeitet einen einzelnen Ticket-Datensatz und versendet die E-Mail.
     *
     * @param TicketData $ticket    Der Ticket-Datensatz
     * @param array      $emailConfig Die E-Mail-Konfiguration
     * @param string     $templateContent Der Inhalt der E-Mail-Vorlage
     * @param bool       $testMode  Gibt an, ob im Testmodus gesendet wird
     * @param \DateTime  $timestamp Der Zeitstempel der Sendung
     */
    private function processTicketEmail(
        TicketData $ticket,
        array $emailConfig,
        string $templateContent,
        bool $testMode,
        \DateTime $timestamp
    ): EmailSent {
        $user = $this->userRepository->findByUsername((string) $ticket->username);

        // Erstelle das E-Mail-Protokoll
        $emailRecord = new EmailSent();
        $emailRecord->setTicketId($ticket->ticketId);
        $emailRecord->setUsername((string) $ticket->username);
        $emailRecord->setTimestamp(clone $timestamp);
        $emailRecord->setTestMode($testMode);
        $emailRecord->setTicketName($ticket->ticketName);
        
        // Wenn kein Benutzer gefunden wurde
        if (!$user) {
            $emailRecord->setEmail(new EmailAddress('example@example.com'));
            $emailRecord->setSubject('');
            $emailRecord->setStatus(EmailStatus::error('no email found'));
            
            // 🔥 EVENT: E-Mail übersprungen (kein Benutzer gefunden)
            $this->eventDispatcher->dispatch(new EmailSkippedEvent(
            $emailRecord->getTicketId(),
            $emailRecord->getUsername(),
            $emailRecord->getEmail(),
            $emailRecord->getStatus(),
            $emailRecord->getTestMode(),
            $emailRecord->getTicketName()
        ));
            
            return $emailRecord;
        }
        
        // E-Mail-Einstellungen
        // prefer EmailAddress instances in config; keep union types for compatibility
        $recipientEmail = $testMode ? $emailConfig['testEmail'] : $user->getEmail();
        $subject = str_replace('{{ticketId}}', (string) $ticket->ticketId, $emailConfig['subject']);
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
            $emailRecord->setStatus(EmailStatus::sent());
            
            // 🔥 EVENT: E-Mail erfolgreich versendet
            $this->eventDispatcher->dispatch(new EmailSentEvent(
                $emailRecord->getTicketId(),
                $emailRecord->getUsername(),
                $emailRecord->getEmail(),
                $emailRecord->getSubject(),
                $emailRecord->getTestMode(),
                $emailRecord->getTicketName()
            ));
            
        } catch (\Exception $e) {
            $emailRecord->setStatus(EmailStatus::error($e->getMessage()));
            
            // 🔥 EVENT: E-Mail-Versand fehlgeschlagen
            $this->eventDispatcher->dispatch(new EmailFailedEvent(
                $emailRecord->getTicketId(),
                $emailRecord->getUsername(),
                $emailRecord->getEmail(),
                $emailRecord->getSubject(),
                $e->getMessage(),
                $emailRecord->getTestMode(),
                $emailRecord->getTicketName()
            ));
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
     * @param string    $template       Die E-Mail-Vorlage
     * @param TicketData $ticket        Der Ticket-Datensatz
     * @param mixed     $user           Der Benutzer-Datensatz
     * @param string    $ticketBaseUrl  Die Basis-URL für Ticket-Links
     * @param bool      $testMode       Gibt an, ob im Testmodus gesendet wird
     * @return string   Der vorbereitete E-Mail-Inhalt
     */
    private function prepareEmailContent(
        string $template,
        TicketData $ticket,
        $user,
        string $ticketBaseUrl,
        bool $testMode
    ): string {
        $ticketLink = rtrim($ticketBaseUrl, '/') . '/' . (string) $ticket->ticketId;
        $emailBody = $template;
        $emailBody = str_replace('{{ticketId}}', (string) $ticket->ticketId, $emailBody);
        $emailBody = str_replace('{{ticketLink}}', $ticketLink, $emailBody);
        $emailBody = str_replace('{{ticketName}}', $ticket->ticketName ? (string) $ticket->ticketName : '', $emailBody);
        $emailBody = str_replace('{{username}}', (string) $ticket->username, $emailBody);
        
        // Füge das Fälligkeitsdatum hinzu (aktuelles Datum + 7 Tage) im deutschen Format
        $dueDate = new \DateTime();
        $dueDate->modify('+7 days');
        $germanMonths = [
            1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni',
            7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'
        ];
        $formattedDueDate = $dueDate->format('d') . '. ' . $germanMonths[(int)$dueDate->format('n')] . ' ' . $dueDate->format('Y');
        $emailBody = str_replace('{{dueDate}}', $formattedDueDate, $emailBody);
        
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
    /**
     * @param EmailAddress|string $recipient
     */
    private function sendEmail(
        EmailAddress|string $recipient,
        string $subject,
        string $content,
        array $config
    ): void {
        $email = (new Email())
            ->from(new Address((string) ($config['senderEmail'] ?? 'noreply@example.com'), $config['senderName']))
            ->to((string) $recipient)
            ->subject($subject);
        
        // Prüfen, ob der Inhalt HTML ist
        if (strpos($content, '<html') !== false || strpos($content, '<p>') !== false || strpos($content, '<div>') !== false) {
            $email->html($content);
        } else {
            $email->text($content);
        }
            
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
            'subject' => $this->params->get('app.email_subject') ?? 'Feedback zu Ticket {{ticketId}}',
            'ticketBaseUrl' => $this->params->get('app.ticket_base_url') ?? 'https://www.ticket.de',
            'testEmail' => $this->params->get('app.test_email') ?? 'test@example.com',
            'useCustomSMTP' => false,
        ];
          // Wenn eine Konfiguration vorhanden ist, verwende sie
        if ($config) {
            // Keep EmailAddress instance for senderEmail
            $emailConfig['senderEmail'] = $config->getSenderEmail();
            $emailConfig['senderName'] = $config->getSenderName();
            $emailConfig['ticketBaseUrl'] = $config->getTicketBaseUrl();
            $emailConfig['useCustomSMTP'] = true;
            $emailConfig['smtpDSN'] = $config->getDSN();
        } else {
            $emailConfig['senderEmail'] = $this->params->get('app.sender_email') ?? 'noreply@example.com';
            $emailConfig['senderName'] = $this->params->get('app.sender_name') ?? 'Ticket-System';
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
        // Prüfe zuerst, ob ein HTML-Template existiert
        $htmlPath = $this->projectDir . '/templates/emails/email_template.html';
        if (file_exists($htmlPath)) {
            return file_get_contents($htmlPath);
        }
        
        // Fallback auf Text-Template
        $templatePath = $this->projectDir . '/templates/emails/email_template.txt';
        
        if (!file_exists($templatePath)) {
            // Standardtemplate zurückgeben, wenn keine Datei vorhanden ist
            return "<p>Sehr geehrte(r) {{username}},</p>

<p>wir möchten gerne Ihre Meinung zu dem kürzlich bearbeiteten Ticket erfahren:</p>

<p><strong>Ticket-Nr:</strong> {{ticketId}}<br>
<strong>Betreff:</strong> {{ticketName}}</p>

<p>Um das Ticket anzusehen und Feedback zu geben, <a href=\"{{ticketLink}}\">klicken Sie bitte hier</a>.</p>

<p>Bitte beantworten Sie die Umfrage bis zum {{dueDate}}.</p>

<p>Vielen Dank für Ihre Rückmeldung!</p>

<p>Mit freundlichen Grüßen<br>
Ihr Support-Team</p>";
        }
        
        return file_get_contents($templatePath);
    }
}
