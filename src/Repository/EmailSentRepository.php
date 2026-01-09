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
    private const COUNT_SELECT = 'COUNT(e.id)';
    
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
     * Erstellt einen QueryBuilder für das Versandprotokoll mit optionaler Filterung
     *
     * Mit diesem QueryBuilder können E-Mails nach Test- oder Live-Modus gefiltert
     * werden. Die Ergebnisse werden standardmäßig nach Zeitstempel absteigend
     * sortiert, sodass die neuesten Einträge zuerst erscheinen.
     *
     * @param string $filter Filteroption ('all', 'live' oder 'test')
     * @return \Doctrine\ORM\QueryBuilder Der konfigurierte QueryBuilder
     */
    public function createFilteredQueryBuilder(string $filter = 'all'): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('e')
            ->orderBy('e.timestamp', 'DESC');

        if ($filter === 'live') {
            $qb->andWhere('e.testMode = false');
        } elseif ($filter === 'test') {
            $qb->andWhere('e.testMode = true');
        }

        return $qb;
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
            $ticketIdString = (string) $emailSent->getTicketId();
            // Nur das neueste Record pro Ticket-ID behalten
            if (!isset($existingTickets[$ticketIdString]) ||
                $emailSent->getTimestamp() > $existingTickets[$ticketIdString]->getTimestamp()) {
                $existingTickets[$ticketIdString] = $emailSent;
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
            ->select(self::COUNT_SELECT)
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
            ->select(self::COUNT_SELECT)
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
            ->select(self::COUNT_SELECT)
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

    /**
     * Holt die Anzahl einzigartiger Benutzer pro Monat für die letzten 6 Monate
     *
     * Gibt ein Array zurück, das für jeden der letzten 6 Monate die Anzahl
     * der einzigartigen Benutzer enthält, die eine E-Mail erhalten haben.
     *
     * @return array Array mit monatlichen Statistiken [['month' => 'YYYY-MM', 'unique_users' => int], ...]
     */
    public function getMonthlyUserStatistics(): array
    {
        // Berechne das Datum vor 5 Monaten (zusammen mit dem aktuellen Monat = 6 Monate)
        $fiveMonthsAgo = (new \DateTime())->modify('-5 months first day of this month')->setTime(0, 0, 0);

        // Hole Daten für die letzten 6 Monate
        $qb = $this->createQueryBuilder('e')
            ->select("DATE_FORMAT(e.timestamp, '%Y-%m') as month, COUNT(DISTINCT e.username) as unique_users")
            ->where('e.timestamp >= :fiveMonthsAgo')
            ->andWhere('e.status = :status')
            ->setParameter('fiveMonthsAgo', $fiveMonthsAgo)
            ->setParameter('status', 'sent')
            ->groupBy('month')
            ->orderBy('month', 'ASC');

        $results = $qb->getQuery()->getResult();

        // Erstelle ein Lookup-Array für schnelleren Zugriff (O(m) statt O(n*m))
        $resultsByMonth = [];
        foreach ($results as $result) {
            $resultsByMonth[$result['month']] = (int) $result['unique_users'];
        }

        // Erstelle ein vollständiges Array für die letzten 6 Monate (auch Monate ohne Daten)
        $monthlyStats = [];
        $currentDate = new \DateTime();
        
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = clone $currentDate;
            $monthDate->modify("-$i months");
            $monthKey = $monthDate->format('Y-m');
            
            // Verwende Lookup-Array für O(1) Zugriff
            $monthlyStats[] = [
                'month' => $monthKey,
                'unique_users' => $resultsByMonth[$monthKey] ?? 0
            ];
        }

        return $monthlyStats;
    }
}