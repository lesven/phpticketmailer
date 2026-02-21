<?php
/**
 * EmailService.php
 *
 * Orchestrator für den Ticket-E-Mail-Versand.
 *
 * Koordiniert die Zusammenarbeit der spezialisierten Services:
 * - EmailConfigFactory: Konfigurationsauflösung
 * - EmailContentRenderer: Template-Auflösung und Placeholder-Ersetzung
 * - EmailSender: Tatsächlicher E-Mail-Versand
 * - EmailRecordFactory: Erstellung und Persistierung von EmailSent-Records
 *
 * Die Domain-Logik (Skip-Bedingungen, Duplikatsprüfung) verbleibt hier
 * als Teil der Orchestrierung.
 *
 * @package App\Service
 */

namespace App\Service;

use App\Dto\EmailConfig;
use App\Entity\EmailSent;
use App\ValueObject\EmailAddress;
use App\ValueObject\EmailStatus;
use App\ValueObject\TicketData;
use App\Repository\UserRepository;
use App\Repository\EmailSentRepository;
use App\Event\Email\EmailSentEvent;
use App\Event\Email\EmailFailedEvent;
use App\Event\Email\EmailSkippedEvent;
use App\Event\Email\BulkEmailCompletedEvent;
use App\Service\Email\EmailConfigFactory;
use App\Service\Email\EmailContentRenderer;
use App\Service\Email\EmailSender;
use App\Service\Email\EmailRecordFactory;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class EmailService
{
    public function __construct(
        private readonly EmailConfigFactory $configFactory,
        private readonly EmailContentRenderer $contentRenderer,
        private readonly EmailSender $emailSender,
        private readonly EmailRecordFactory $recordFactory,
        private readonly UserRepository $userRepository,
        private readonly EmailSentRepository $emailSentRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Sendet E-Mails für alle übergebenen Ticket-Datensätze.
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
        $emailConfig = $this->configFactory->create($testMode, $customTestEmail);
        $globalTemplateContent = $this->contentRenderer->getGlobalTemplate();

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

            $sentEmails[] = $this->recordFactory->persist($emailRecord);
            $processedTicketIds[$ticketIdStr] = true;
        }

        $this->recordFactory->flushRemaining();
        $this->dispatchBulkCompletedEvent($ticketData, $sentEmails, $testMode, $startTime);

        return $sentEmails;
    }

    /**
     * Gibt die Template-Debug-Infos pro Ticket zurück.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getTemplateDebugInfo(): array
    {
        return $this->contentRenderer->getTemplateDebugInfo();
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
        $templateContent = $this->contentRenderer->resolveTemplateForTicket($ticketObj, $globalTemplateContent);

        return $this->processTicketEmail($ticketObj, $emailConfig, $templateContent, $testMode, $currentTime);
    }

    /**
     * Erstellt einen Skip-Record und dispatcht das zugehörige Event.
     */
    private function createAndDispatchSkippedRecord(
        TicketData $ticketData,
        \DateTime $timestamp,
        bool $testMode,
        EmailStatus $status
    ): EmailSent {
        $emailRecord = $this->recordFactory->createSkippedRecord($ticketData, $timestamp, $testMode, $status);

        $this->eventDispatcher->dispatch(new EmailSkippedEvent(
            $ticketData,
            $emailRecord->getEmail(),
            $emailRecord->getStatus(),
            $emailRecord->getTestMode()
        ));

        return $emailRecord;
    }

    /**
     * Verarbeitet einen einzelnen Ticket-Datensatz und versendet die E-Mail.
     */
    private function processTicketEmail(
        TicketData $ticket,
        EmailConfig $emailConfig,
        string $templateContent,
        bool $testMode,
        \DateTime $timestamp
    ): EmailSent {
        $user = $this->userRepository->findByUsername((string) $ticket->username);
        $emailRecord = $this->recordFactory->createSendRecord($ticket, $timestamp, $testMode);

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

        $emailBody = $this->contentRenderer->render(
            $templateContent,
            $ticket,
            $user,
            $emailConfig->ticketBaseUrl,
            $testMode
        );

        try {
            $this->emailSender->send($recipientEmail, $subject, $emailBody, $emailConfig);
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
