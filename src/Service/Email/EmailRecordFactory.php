<?php

namespace App\Service\Email;

use App\Entity\EmailSent;
use App\Repository\UserRepository;
use App\ValueObject\EmailAddress;
use App\ValueObject\EmailStatus;
use App\ValueObject\TicketData;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Factory für EmailSent-Records mit integrierter Persistierung.
 *
 * Kapselt die Erstellung von EmailSent-Entitäten für alle Szenarien
 * (Skip, Send, Error) sowie die fehlertolerante Persistierung.
 */
class EmailRecordFactory
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Erstellt ein EmailSent-Record für übersprungene Tickets.
     */
    public function createSkippedRecord(
        TicketData $ticketData,
        \DateTime $timestamp,
        bool $testMode,
        EmailStatus|string $status
    ): EmailSent {
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
     * Erstellt ein Basis-EmailSent-Record für den Versandprozess.
     */
    public function createSendRecord(
        TicketData $ticket,
        \DateTime $timestamp,
        bool $testMode
    ): EmailSent {
        $emailRecord = new EmailSent();
        $emailRecord->setTicketId($ticket->ticketId);
        $emailRecord->setUsername((string) $ticket->username);
        $emailRecord->setTimestamp(clone $timestamp);
        $emailRecord->setTestMode($testMode);
        $emailRecord->setTicketName($ticket->ticketName);
        $emailRecord->setTicketCreated($ticket->created ?? null);

        return $emailRecord;
    }

    /**
     * Persistiert einen EmailSent-Record mit Fehlerbehandlung.
     *
     * Bei einem Fehler wird ein Fehler-Record erstellt und stattdessen persistiert.
     */
    public function persist(EmailSent $emailRecord): EmailSent
    {
        try {
            $this->entityManager->persist($emailRecord);
            $this->entityManager->flush();
            return $emailRecord;
        } catch (\Exception $e) {
            return $this->persistErrorFallback($emailRecord, $e);
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
            error_log('Error saving remaining email records: ' . $e->getMessage());
        }
    }

    /**
     * Erstellt und persistiert einen Fehler-Fallback-Record wenn die
     * ursprüngliche Persistierung fehlschlägt.
     */
    private function persistErrorFallback(EmailSent $originalRecord, \Exception $error): EmailSent
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
}
