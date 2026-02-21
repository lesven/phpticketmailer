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

use App\Dto\EmailConfig;
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
use App\Service\TemplateService;

class EmailService
{
    private MailerInterface $mailer;
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private SMTPConfigRepository $smtpConfigRepository;
    private EmailSentRepository $emailSentRepository;
    private ParameterBagInterface $params;
    private string $projectDir;
    private TemplateService $templateService;

    /** @var array<string, array<string, mixed>> */
    private array $templateDebugInfo = [];

    public function __construct(
        MailerInterface $mailer,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        SMTPConfigRepository $smtpConfigRepository,
        EmailSentRepository $emailSentRepository,
        ParameterBagInterface $params,
        string $projectDir,
        private readonly EventDispatcherInterface $eventDispatcher,
        TemplateService $templateService
    ) {
        $this->mailer = $mailer;
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;
        $this->smtpConfigRepository = $smtpConfigRepository;
        $this->emailSentRepository = $emailSentRepository;
        $this->params = $params;
        $this->projectDir = $projectDir;
        $this->templateService = $templateService;
    }

    /**
     * Sendet E-Mails für alle übergebenen Ticket-Datensätze
     *
     * @param TicketData[] $ticketData Liste der zu verarbeitenden Tickets
     * @param bool $testMode Gibt an, ob die E-Mails im Testmodus gesendet werden sollen
     * @param string|null $customTestEmail Optionale Test-E-Mail-Adresse für den Testmodus
     * @return EmailSent[] Array mit allen erstellten EmailSent-Entitäten
     */
    public function sendTicketEmails(array $ticketData, bool $testMode = false, ?string $customTestEmail = null): array
    {
        return $this->sendTicketEmailsWithDuplicateCheck($ticketData, $testMode, true, $customTestEmail);
    }

    /**
     * Sendet E-Mails für alle übergebenen Ticket-Datensätze mit Duplikatsprüfung.
     *
     * @param TicketData[] $ticketData Liste der Ticket-Daten
     * @param bool $testMode Gibt an, ob die E-Mails im Testmodus gesendet werden sollen
     * @param bool $forceResend Gibt an, ob bereits verarbeitete Tickets erneut versendet werden sollen
     * @param string|null $customTestEmail Optionale Test-E-Mail-Adresse für den Testmodus
     * @return EmailSent[] Array mit allen erstellten EmailSent-Entitäten
     */
    public function sendTicketEmailsWithDuplicateCheck(array $ticketData, bool $testMode = false, bool $forceResend = false, ?string $customTestEmail = null): array
    {
        $startTime = microtime(true);
        $sentEmails = [];
        $processedTicketIds = [];
        $currentTime = new \DateTime();
        $emailConfig = $this->buildEmailConfig($testMode, $customTestEmail);
        $globalTemplateContent = $this->getEmailTemplate();

        $existingTickets = $forceResend
            ? []
            : $this->loadExistingTickets($ticketData);

        foreach ($ticketData as $ticket) {
            $ticketObj = $this->normalizeTicket($ticket);
            $ticketIdStr = (string) $ticketObj->ticketId;

            $emailRecord = $this->processSingleTicket(
                $ticketObj,
                $emailConfig,
                $globalTemplateContent,
                $testMode,
                $currentTime,
                $processedTicketIds,
                $existingTickets,
                $forceResend
            );

            $sentEmails[] = $this->persistEmailRecord($emailRecord, $ticketObj);
            $processedTicketIds[$ticketIdStr] = true;
        }

        $this->flushRemaining();
        $this->dispatchBulkCompletedEvent($ticketData, $sentEmails, $testMode, $startTime);

        return $sentEmails;
    }

    /**
     * Verarbeitet ein einzelnes Ticket und gibt den passenden EmailSent-Record zurück.
     *
     * Prüft nacheinander alle Skip-Bedingungen (CSV-Duplikat, DB-Duplikat,
     * Umfrage-Ausschluss) und versendet die E-Mail nur wenn keine zutrifft.
     */
    private function processSingleTicket(
        TicketData $ticketObj,
        EmailConfig $emailConfig,
        string $globalTemplateContent,
        bool $testMode,
        \DateTime $currentTime,
        array $processedTicketIds,
        array $existingTickets,
        bool $forceResend
    ): EmailSent {
        $ticketIdStr = (string) $ticketObj->ticketId;

        // 1. Duplikat innerhalb der aktuellen CSV
        if (isset($processedTicketIds[$ticketIdStr])) {
            return $this->createAndDispatchSkippedRecord(
                $ticketObj, $currentTime, $testMode, EmailStatus::duplicateInCsv()
            );
        }

        // 2. Bereits in DB verarbeitet
        if (!$forceResend && isset($existingTickets[$ticketIdStr])) {
            return $this->createAndDispatchSkippedRecord(
                $ticketObj, $currentTime, $testMode,
                EmailStatus::alreadyProcessed($existingTickets[$ticketIdStr]->getTimestamp())
            );
        }

        // 3. Benutzer von Umfragen ausgeschlossen
        $user = $this->userRepository->findByUsername((string) $ticketObj->username);
        if ($user && $user->isExcludedFromSurveys()) {
            return $this->createAndDispatchSkippedRecord(
                $ticketObj, $currentTime, $testMode, EmailStatus::excludedFromSurvey()
            );
        }

        // 4. Template auswählen und E-Mail versenden
        $templateContent = $this->resolveTemplateContent($ticketObj, $globalTemplateContent);

        return $this->processTicketEmail($ticketObj, $emailConfig, $templateContent, $testMode, $currentTime);
    }

    /**
     * Erstellt einen Skip-Record und dispatcht das zugehörige Event.
     *
     * Eliminiert die vorherige Codeduplizierung, bei der jede Skip-Bedingung
     * denselben Block aus Record-Erstellung + Event-Dispatching wiederholte.
     */
    private function createAndDispatchSkippedRecord(
        TicketData $ticketData,
        \DateTime $timestamp,
        bool $testMode,
        EmailStatus $status
    ): EmailSent {
        $emailRecord = $this->createSkippedEmailRecord($ticketData, $timestamp, $testMode, $status);

        $this->eventDispatcher->dispatch(new EmailSkippedEvent(
            $ticketData,
            $emailRecord->getEmail(),
            $emailRecord->getStatus(),
            $emailRecord->getTestMode()
        ));

        return $emailRecord;
    }

    /**
     * Erstellt ein EmailSent-Record für übersprungene Tickets.
     */
    private function createSkippedEmailRecord(TicketData $ticketData, \DateTime $timestamp, bool $testMode, EmailStatus|string $status): EmailSent
    {
        $user = $this->userRepository->findByUsername((string) $ticketData->username);

        $emailRecord = new EmailSent();
        $emailRecord->setTicketId($ticketData->ticketId);
        $emailRecord->setUsername((string) $ticketData->username);
        $emailRecord->setEmail($user ? $user->getEmail() : null);
        $emailRecord->setSubject('');
        $emailRecord->setStatus($status);
        $emailRecord->setTimestamp(clone $timestamp);
        $emailRecord->setTestMode($testMode);
        $emailRecord->setTicketName($ticketData->ticketName);
        $emailRecord->setTicketCreated($ticketData->created ?? null);

        return $emailRecord;
    }

    /**
     * Verarbeitet einen einzelnen Ticket-Datensatz und versendet die E-Mail.
     *
     * Findet den Benutzer, erstellt die E-Mail, sendet sie und protokolliert
     * den Versand. Bei Fehlern wird der Fehlerstatus gespeichert.
     */
    private function processTicketEmail(
        TicketData $ticket,
        EmailConfig $emailConfig,
        string $templateContent,
        bool $testMode,
        \DateTime $timestamp
    ): EmailSent {
        $user = $this->userRepository->findByUsername((string) $ticket->username);

        $emailRecord = new EmailSent();
        $emailRecord->setTicketId($ticket->ticketId);
        $emailRecord->setUsername((string) $ticket->username);
        $emailRecord->setTimestamp(clone $timestamp);
        $emailRecord->setTestMode($testMode);
        $emailRecord->setTicketName($ticket->ticketName);
        $emailRecord->setTicketCreated($ticket->created ?? null);

        if (!$user) {
            $emailRecord->setEmail(new EmailAddress('example@example.com'));
            $emailRecord->setSubject('');
            $emailRecord->setStatus(EmailStatus::error('no email found'));

            $this->eventDispatcher->dispatch(new EmailSkippedEvent(
                $ticket,
                $emailRecord->getEmail(),
                $emailRecord->getStatus(),
                $emailRecord->getTestMode()
            ));

            return $emailRecord;
        }

        $recipientEmail = $testMode ? $emailConfig->testEmail : $user->getEmail();
        $subject = str_replace('{{ticketId}}', (string) $ticket->ticketId, $emailConfig->subject);
        $emailRecord->setEmail($recipientEmail);
        $emailRecord->setSubject($subject);

        $emailBody = $this->prepareEmailContent(
            $templateContent,
            $ticket,
            $user,
            $emailConfig->ticketBaseUrl,
            $testMode
        );

        try {
            $this->sendEmail($recipientEmail, $subject, $emailBody, $emailConfig);
            $emailRecord->setStatus(EmailStatus::sent());

            $this->eventDispatcher->dispatch(new EmailSentEvent(
                $ticket,
                $emailRecord->getEmail(),
                $emailRecord->getSubject(),
                $emailRecord->getTestMode()
            ));

        } catch (\Exception $e) {
            $emailRecord->setStatus(EmailStatus::error($e->getMessage()));

            $this->eventDispatcher->dispatch(new EmailFailedEvent(
                $ticket,
                $emailRecord->getEmail(),
                $emailRecord->getSubject(),
                $e->getMessage(),
                $emailRecord->getTestMode()
            ));
        }

        return $emailRecord;
    }

    /**
     * Persistiert einen EmailSent-Record mit Fehlerbehandlung.
     *
     * Bei einem Fehler wird ein Fehler-Record erstellt und stattdessen persistiert.
     * Dies eliminiert die duplizierte try/catch-Logik aus der Hauptschleife.
     */
    private function persistEmailRecord(EmailSent $emailRecord, TicketData $ticketObj): EmailSent
    {
        try {
            $this->entityManager->persist($emailRecord);
            $this->entityManager->flush();
            return $emailRecord;
        } catch (\Exception $e) {
            return $this->persistErrorFallbackRecord($emailRecord, $e);
        }
    }

    /**
     * Erstellt und persistiert einen Fehler-Fallback-Record wenn die
     * ursprüngliche Persistierung fehlschlägt.
     */
    private function persistErrorFallbackRecord(EmailSent $originalRecord, \Exception $error): EmailSent
    {
        $errorRecord = new EmailSent();
        $errorRecord->setTicketId($originalRecord->getTicketId());
        $errorRecord->setUsername($originalRecord->getUsername());
        $errorRecord->setTimestamp($originalRecord->getTimestamp());
        $errorRecord->setTestMode($originalRecord->getTestMode());
        $errorRecord->setStatus(EmailStatus::error('database save failed - ' . $error->getMessage()));
        $errorRecord->setEmail($originalRecord->getEmail());
        $errorRecord->setSubject($originalRecord->getSubject());
        $errorRecord->setTicketName($originalRecord->getTicketName());
        $errorRecord->setTicketCreated($originalRecord->getTicketCreated());

        try {
            $this->entityManager->persist($errorRecord);
            $this->entityManager->flush();
            return $errorRecord;
        } catch (\Exception $innerE) {
            error_log('Critical: Could not save error record: ' . $innerE->getMessage());
            return $errorRecord;
        }
    }

    /**
     * Bereitet den E-Mail-Inhalt vor, indem Platzhalter ersetzt werden.
     */
    private function prepareEmailContent(
        string $template,
        TicketData $ticketData,
        $user,
        string $ticketBaseUrl,
        bool $testMode
    ): string {
        $ticketLink = rtrim($ticketBaseUrl, '/') . '/' . (string) $ticketData->ticketId;
        $emailBody = $template;
        $emailBody = str_replace('{{ticketId}}', (string) $ticketData->ticketId, $emailBody);
        $emailBody = str_replace('{{ticketLink}}', $ticketLink, $emailBody);
        $emailBody = str_replace('{{ticketName}}', (string) $ticketData->ticketName, $emailBody);
        $emailBody = str_replace('{{username}}', (string) $ticketData->username, $emailBody);
        $emailBody = str_replace('{{created}}', $ticketData->created ?? '', $emailBody);

        $dueDate = new \DateTime();
        $dueDate->modify('+7 days');
        $germanMonths = [
            1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni',
            7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'
        ];
        $formattedDueDate = $dueDate->format('d') . '. ' . $germanMonths[(int)$dueDate->format('n')] . ' ' . $dueDate->format('Y');
        $emailBody = str_replace('{{dueDate}}', $formattedDueDate, $emailBody);

        if ($testMode) {
            $emailBody = "*** TESTMODUS - E-Mail wäre an {$user->getEmail()} gegangen ***\n\n" . $emailBody;
        }

        return $emailBody;
    }

    /**
     * Sendet die E-Mail über den konfigurierten Transport.
     */
    private function sendEmail(
        EmailAddress|string $recipient,
        string $subject,
        string $content,
        EmailConfig $config
    ): void {
        $email = (new Email())
            ->from(new Address((string) $config->senderEmail, $config->senderName))
            ->to((string) $recipient)
            ->subject($subject);

        if (strpos($content, '<html') !== false || strpos($content, '<p>') !== false || strpos($content, '<div>') !== false) {
            $email->html($content);
        } else {
            $email->text($content);
        }

        if ($config->useCustomSMTP) {
            $transport = Transport::fromDsn($config->smtpDSN);
            $transport->send($email);
        } else {
            $this->mailer->send($email);
        }
    }

    /**
     * Erstellt die typisierte E-Mail-Konfiguration aus DB oder Parametern.
     */
    private function buildEmailConfig(bool $testMode, ?string $customTestEmail): EmailConfig
    {
        $config = $this->smtpConfigRepository->getConfig();

        $subject = $this->params->get('app.email_subject') ?? 'Feedback zu Ticket {{ticketId}}';
        $ticketBaseUrl = $this->params->get('app.ticket_base_url') ?? 'https://www.ticket.de';
        $testEmail = $this->params->get('app.test_email') ?? 'test@example.com';

        if ($testMode && $customTestEmail !== null && !empty(trim($customTestEmail))) {
            $testEmail = trim($customTestEmail);
        }

        if ($config) {
            return new EmailConfig(
                subject: $subject,
                ticketBaseUrl: $config->getTicketBaseUrl(),
                senderEmail: $config->getSenderEmail(),
                senderName: $config->getSenderName(),
                testEmail: $testEmail,
                useCustomSMTP: true,
                smtpDSN: $config->getDSN(),
            );
        }

        return new EmailConfig(
            subject: $subject,
            ticketBaseUrl: $ticketBaseUrl,
            senderEmail: $this->params->get('app.sender_email') ?? 'noreply@example.com',
            senderName: $this->params->get('app.sender_name') ?? 'Ticket-System',
            testEmail: $testEmail,
            useCustomSMTP: false,
        );
    }

    /**
     * Lade die E-Mail-Konfiguration (Backward-Compatibility für Tests via Reflection).
     *
     * @return array Die E-Mail-Konfiguration als assoziatives Array
     * @deprecated Verwende buildEmailConfig() stattdessen
     */
    private function getEmailConfiguration(): array
    {
        $config = $this->buildEmailConfig(false, null);

        return [
            'subject' => $config->subject,
            'ticketBaseUrl' => $config->ticketBaseUrl,
            'testEmail' => $config->testEmail,
            'useCustomSMTP' => $config->useCustomSMTP,
            'senderEmail' => $config->senderEmail,
            'senderName' => $config->senderName,
            'smtpDSN' => $config->smtpDSN,
        ];
    }

    /**
     * Lädt die E-Mail-Vorlage aus der Datei oder erstellt eine Standard-Vorlage.
     */
    private function getEmailTemplate(): string
    {
        $htmlPath = $this->projectDir . '/templates/emails/email_template.html';
        if (file_exists($htmlPath)) {
            return file_get_contents($htmlPath);
        }

        $templatePath = $this->projectDir . '/templates/emails/email_template.txt';

        if (!file_exists($templatePath)) {
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

    /**
     * Gibt die Template-Debug-Infos pro Ticket zurück.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getTemplateDebugInfo(): array
    {
        return $this->templateDebugInfo;
    }

    /**
     * Normalisiert ein Ticket (Array oder TicketData) zu einem TicketData-Objekt.
     */
    private function normalizeTicket(TicketData|array $ticket): TicketData
    {
        if (is_array($ticket)) {
            return TicketData::fromStrings(
                $ticket['ticketId'],
                $ticket['username'],
                $ticket['ticketName'] ?? '',
                $ticket['created'] ?? null
            );
        }

        return $ticket;
    }

    /**
     * Lädt bereits verarbeitete Tickets aus der Datenbank.
     *
     * @param array $ticketData Liste der Ticket-Daten
     * @return array<string, EmailSent> Mapping von Ticket-ID zu existierendem Record
     */
    private function loadExistingTickets(array $ticketData): array
    {
        $ticketIds = array_map(function ($ticket) {
            return is_array($ticket) ? $ticket['ticketId'] : (string) $ticket->ticketId;
        }, $ticketData);

        return $this->emailSentRepository->findExistingTickets($ticketIds);
    }

    /**
     * Wählt das Template für ein Ticket aus und speichert Debug-Infos.
     */
    private function resolveTemplateContent(TicketData $ticketObj, string $globalFallback): string
    {
        $ticketIdStr = (string) $ticketObj->ticketId;
        $resolved = $this->templateService->resolveTemplateForTicketDate($ticketObj->created);
        $templateContent = $resolved['content'] ?? '';
        $this->templateDebugInfo[$ticketIdStr] = $resolved['debug'] ?? [];

        if ($templateContent === '' || trim($templateContent) === '') {
            $templateContent = $globalFallback;
            $selectionMethod = $this->templateDebugInfo[$ticketIdStr]['selectionMethod'] ?? '';
            $this->templateDebugInfo[$ticketIdStr]['selectionMethod'] = $selectionMethod . ' → fallback_global';
        }

        return $templateContent;
    }

    /**
     * Flusht verbleibende Entity-Manager-Änderungen.
     */
    private function flushRemaining(): void
    {
        try {
            $this->entityManager->flush();
        } catch (\Exception $e) {
            error_log('Error saving remaining email records: ' . $e->getMessage());
        }
    }

    /**
     * Dispatcht das BulkEmailCompletedEvent mit Statistiken.
     *
     * @param array $ticketData Ursprüngliche Ticket-Liste
     * @param EmailSent[] $sentEmails Alle verarbeiteten Records
     */
    private function dispatchBulkCompletedEvent(array $ticketData, array $sentEmails, bool $testMode, float $startTime): void
    {
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
    }
}
