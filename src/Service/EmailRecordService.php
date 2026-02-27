<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\EmailSent;
use App\Entity\User;
use App\Event\Email\EmailSkippedEvent;
use App\Repository\UserRepository;
use App\ValueObject\EmailAddress;
use App\ValueObject\EmailStatus;
use App\ValueObject\TicketData;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Verantwortlich für Erstellung, Persistierung und Fehlerbehandlung
 * von EmailSent-Records in der Datenbank.
 *
 * Kapselt die gesamte Record-Lifecycle-Logik und das Dispatching
 * von Skip-Events.
 */
final class EmailRecordService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Erstellt einen Skip-Record und dispatcht das zugehörige EmailSkippedEvent.
     */
    public function createAndDispatchSkippedRecord(
        TicketData $ticketData,
        \DateTime $timestamp,
        bool $testMode,
        EmailStatus $status,
        ?User $user = null,
    ): EmailSent {
        $emailRecord = $this->createSkippedEmailRecord($ticketData, $timestamp, $testMode, $status, $user);

        $this->eventDispatcher->dispatch(new EmailSkippedEvent(
            $ticketData,
            $emailRecord->getEmail(),
            $emailRecord->getStatus(),
            $emailRecord->getTestMode()
        ));

        return $emailRecord;
    }

    /**
     * Erstellt einen EmailSent-Record für übersprungene Tickets.
     *
     * Wenn ein User-Objekt übergeben wird, wird dessen E-Mail verwendet.
     * Andernfalls wird der Benutzer per Username nachgeschlagen.
     */
    public function createSkippedEmailRecord(
        TicketData $ticketData,
        \DateTime $timestamp,
        bool $testMode,
        EmailStatus|string $status,
        ?User $user = null,
    ): EmailSent {
        if ($user === null) {
            $user = $this->userRepository->findByUsername((string) $ticketData->username);
        }

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
     * Persistiert einen EmailSent-Record mit Fehlerbehandlung.
     *
     * Bei einem Fehler wird ein Fehler-Record erstellt und stattdessen persistiert.
     */
    public function persistEmailRecord(EmailSent $emailRecord): EmailSent
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
    public function persistErrorFallbackRecord(EmailSent $originalRecord, \Exception $error): EmailSent
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
            $this->logger->critical('Could not save error record', [
                'error' => $innerE->getMessage(),
                'ticketId' => (string) $originalRecord->getTicketId(),
            ]);
            return $errorRecord;
        }
    }

    /**
     * Flusht verbleibende Entity-Manager-Änderungen.
     */
    public function flushRemaining(): void
    {
        try {
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $this->logger->error('Error saving remaining email records', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
