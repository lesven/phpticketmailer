<?php
/**
 * EmailService.php
 *
 * Orchestrator für den E-Mail-Versandprozess. Koordiniert Template-Auflösung,
 * Duplikatsprüfung, E-Mail-Versand und Record-Persistierung über spezialisierte
 * Sub-Services (EmailTransportService, EmailRecordService, EmailComposer).
 *
 * @package App\Service
 */

namespace App\Service;

use App\Dto\EmailConfig;
use App\Entity\EmailSent;
use App\Entity\User;
use App\ValueObject\EmailStatus;
use App\ValueObject\TicketData;
use App\Repository\UserRepository;
use App\Repository\EmailSentRepository;
use App\Event\Email\EmailSentEvent;
use App\Event\Email\EmailFailedEvent;
use App\Event\Email\BulkEmailCompletedEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use App\ValueObject\EmailAddress;

class EmailService implements EmailServiceInterface
{
    public function __construct(
        private readonly EmailTransportService $transportService,
        private readonly EmailRecordService $recordService,
        private readonly EmailComposer $composer,
        private readonly UserRepository $userRepository,
        private readonly EmailSentRepository $emailSentRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function sendTicketEmailsWithDuplicateCheck(
        array $ticketData,
        bool $testMode = false,
        bool $forceResend = false,
        ?string $customTestEmail = null,
    ): array {
        $startTime = microtime(true);
        $sentEmails = [];
        $processedTicketIds = [];
        $currentTime = new \DateTime();
        $emailConfig = $this->transportService->buildEmailConfig($testMode, $customTestEmail);
        $globalTemplateContent = $this->composer->getDefaultContent();

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

            $sentEmails[] = $this->recordService->persistEmailRecord($emailRecord);
            $processedTicketIds[$ticketIdStr] = true;
        }

        $this->recordService->flushRemaining();
        $this->dispatchBulkCompletedEvent($ticketData, $sentEmails, $testMode, $startTime);

        return $sentEmails;
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplateDebugInfo(): array
    {
        return $this->composer->getTemplateDebugInfo();
    }

    /**
     * Verarbeitet ein einzelnes Ticket und gibt den passenden EmailSent-Record zurück.
     *
     * Prüft nacheinander alle Skip-Bedingungen (CSV-Duplikat, DB-Duplikat,
     * Umfrage-Ausschluss, kein User) und versendet die E-Mail nur wenn keine zutrifft.
     */
    private function processSingleTicket(
        TicketData $ticketObj,
        EmailConfig $emailConfig,
        string $globalTemplateContent,
        bool $testMode,
        \DateTime $currentTime,
        array $processedTicketIds,
        array $existingTickets,
        bool $forceResend,
    ): EmailSent {
        $ticketIdStr = (string) $ticketObj->ticketId;

        // 1. Duplikat innerhalb der aktuellen CSV
        if (isset($processedTicketIds[$ticketIdStr])) {
            return $this->recordService->createAndDispatchSkippedRecord(
                $ticketObj, $currentTime, $testMode, EmailStatus::duplicateInCsv()
            );
        }

        // 2. Bereits in DB verarbeitet
        if (!$forceResend && isset($existingTickets[$ticketIdStr])) {
            return $this->recordService->createAndDispatchSkippedRecord(
                $ticketObj, $currentTime, $testMode,
                EmailStatus::alreadyProcessed($existingTickets[$ticketIdStr]->getTimestamp())
            );
        }

        // 3. User laden (einmal pro Ticket, wird an alle Sub-Methoden weitergereicht)
        $user = $this->userRepository->findByUsername((string) $ticketObj->username);

        // 4. Benutzer von Umfragen ausgeschlossen
        if ($user && $user->isExcludedFromSurveys()) {
            return $this->recordService->createAndDispatchSkippedRecord(
                $ticketObj, $currentTime, $testMode, EmailStatus::excludedFromSurvey(), $user
            );
        }

        // 5. Kein User gefunden — Skip über einheitlichen Pfad
        if (!$user) {
            return $this->recordService->createAndDispatchSkippedRecord(
                $ticketObj, $currentTime, $testMode, EmailStatus::error('no email found')
            );
        }

        // 6. Template auswählen und E-Mail versenden
        $templateContent = $this->composer->resolveTemplateContent($ticketObj, $globalTemplateContent);

        return $this->processTicketEmail($ticketObj, $emailConfig, $templateContent, $testMode, $currentTime, $user);
    }

    /**
     * Verarbeitet einen einzelnen Ticket-Datensatz und versendet die E-Mail.
     *
     * Erstellt die E-Mail, sendet sie und protokolliert den Versand.
     * Bei Fehlern wird der Fehlerstatus gespeichert.
     */
    private function processTicketEmail(
        TicketData $ticket,
        EmailConfig $emailConfig,
        string $templateContent,
        bool $testMode,
        \DateTime $timestamp,
        User $user,
    ): EmailSent {
        $emailRecord = new EmailSent();
        $emailRecord->setTicketId($ticket->ticketId);
        $emailRecord->setUsername((string) $ticket->username);
        $emailRecord->setTimestamp(clone $timestamp);
        $emailRecord->setTestMode($testMode);
        $emailRecord->setTicketName($ticket->ticketName);
        $emailRecord->setTicketCreated($ticket->created ?? null);

        $recipientEmail = $testMode ? $emailConfig->testEmail : $user->getEmail();
        $subject = str_replace('{{ticketId}}', (string) $ticket->ticketId, $emailConfig->subject);
        $emailRecord->setEmail($recipientEmail);
        $emailRecord->setSubject($subject);

        $emailBody = $this->composer->prepareEmailContent(
            $templateContent,
            $ticket,
            $user,
            $emailConfig->ticketBaseUrl,
            $testMode
        );

        try {
            $this->transportService->sendEmail($recipientEmail, $subject, $emailBody, $emailConfig);
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
