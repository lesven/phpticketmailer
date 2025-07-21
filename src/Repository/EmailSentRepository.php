<?php
/**
 * EmailSentRepository.php
 * 
 * Diese Repository-Klasse stellt Methoden zum Zugriff auf EmailSent-Entitäten bereit.
 * Sie bietet Funktionen zum Abrufen von gesendeten E-Mails, insbesondere für die
 * Darstellung im Dashboard und für Reporting-Zwecke.
 * 
 * @package App\Repository
 */

namespace App\Repository;

use App\Entity\EmailSent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository für die EmailSent-Entität
 * 
 * @extends ServiceEntityRepository<EmailSent>
 */
class EmailSentRepository extends ServiceEntityRepository
{
    /**
     * Konstruktor mit Doctrine ManagerRegistry als Dependency
     * 
     * @param ManagerRegistry $registry Die Doctrine ManagerRegistry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailSent::class);
    }

    /**
     * Findet die zuletzt gesendeten E-Mails
     * 
     * Diese Methode ruft die neuesten E-Mail-Protokolle ab, sortiert nach
     * dem Zeitstempel in absteigender Reihenfolge (neueste zuerst).
     * 
     * @param int $limit Die maximale Anzahl der zurückzugebenden Datensätze
     * @return EmailSent[] Array mit EmailSent-Entitäten
     */
    public function findRecentEmails(int $limit = 10): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.timestamp', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Prüft, ob für eine Ticket-ID bereits eine E-Mail versendet wurde
     * 
     * @param string $ticketId Die Ticket-ID, die geprüft werden soll
     * @return EmailSent|null Die gefundene EmailSent-Entität oder null
     */
    public function findByTicketId(string $ticketId): ?EmailSent
    {
        return $this->createQueryBuilder('e')
            ->where('e.ticketId = :ticketId')
            ->setParameter('ticketId', $ticketId)
            ->orderBy('e.timestamp', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Prüft, ob mehrere Ticket-IDs bereits verarbeitet wurden
     * 
     * @param array $ticketIds Array mit Ticket-IDs zum Prüfen
     * @return array Array mit Ticket-IDs als Keys und EmailSent-Entitäten als Values
     */
    public function findExistingTickets(array $ticketIds): array
    {
        $results = $this->createQueryBuilder('e')
            ->where('e.ticketId IN (:ticketIds)')
            ->setParameter('ticketIds', $ticketIds)
            ->getQuery()
            ->getResult();

        $existingTickets = [];
        foreach ($results as $emailSent) {
            // Nur das neueste Record pro Ticket-ID behalten
            if (!isset($existingTickets[$emailSent->getTicketId()]) ||
                $emailSent->getTimestamp() > $existingTickets[$emailSent->getTicketId()]->getTimestamp()) {
                $existingTickets[$emailSent->getTicketId()] = $emailSent;
            }
        }

        return $existingTickets;
    }

    /**
     * Zählt die Gesamtanzahl der erfolgreich versendeten E-Mails
     *
     * @return int Die Anzahl der erfolgreich versendeten E-Mails
     */
    public function countSuccessfulEmails(): int
    {
        return $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.status = :status')
            ->setParameter('status', 'sent')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Zählt die Anzahl einzigartiger Benutzer, die E-Mails erhalten haben
     *
     * @return int Die Anzahl einzigartiger Benutzer
     */
    public function countUniqueRecipients(): int
    {
        return $this->createQueryBuilder('e')
            ->select('COUNT(DISTINCT e.username)')
            ->where('e.status = :status')
            ->setParameter('status', 'sent')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Zählt die Gesamtanzahl aller E-Mail-Versandversuche (erfolgreich und fehlgeschlagen)
     *
     * @return int Die Gesamtanzahl aller E-Mail-Einträge
     */
    public function countTotalEmails(): int
    {
        return $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Zählt die Anzahl fehlgeschlagener E-Mail-Versendungen
     *
     * @return int Die Anzahl der fehlgeschlagenen E-Mails
     */
    public function countFailedEmails(): int
    {
        return $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.status LIKE :status')
            ->setParameter('status', 'error%')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Holt alle E-Mail-Statistiken in einer einzigen Abfrage
     *
     * @return array Assoziatives Array mit allen Statistiken
     */
    public function getEmailStatistics(): array
    {
        // Grundstatistiken
        $total = $this->countTotalEmails();
        $successful = $this->countSuccessfulEmails();
        $failed = $this->countFailedEmails();
        $unique = $this->countUniqueRecipients();

        // Berechne zusätzliche Statistiken
        $skipped = $total - $successful - $failed;

        return [
            'total' => $total,
            'successful' => $successful,
            'failed' => $failed,
            'skipped' => $skipped,
            'unique_recipients' => $unique,
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 1) : 0
        ];
    }
}