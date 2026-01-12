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
            ->setParameter('status', \App\ValueObject\EmailStatus::sent()->getValue())
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
            ->setParameter('status', \App\ValueObject\EmailStatus::sent()->getValue())
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

        // Hole Rohdaten (timestamp + username) und berechne die monatlichen Unique-Counts in PHP.
        // Das umgeht DQL-Funktionsprobleme und ist robust gegen unterschiedliche DB-Umgebungen.
        $qb = $this->createQueryBuilder('e')
            ->select('e.timestamp as ts, e.username as username')
            ->where('e.timestamp >= :fiveMonthsAgo')
            ->andWhere('e.status = :status')
            ->setParameter('fiveMonthsAgo', $fiveMonthsAgo)
->setParameter('status', \App\ValueObject\EmailStatus::sent()->getValue())
            ->orderBy('e.timestamp', 'ASC');

        // Use array hydration to get consistent scalar arrays (ts, username)
        $rows = $qb->getQuery()->getArrayResult();

        // Fallback: if arrayResult is empty for some DB/Doctrine setups, try entity hydration
        if (empty($rows)) {
            $entities = $this->createQueryBuilder('e')
                ->select('e')
                ->where('e.timestamp >= :fiveMonthsAgo')
                ->andWhere('e.status = :status')
                ->setParameter('fiveMonthsAgo', $fiveMonthsAgo)
                ->setParameter('status', 'sent')
                ->orderBy('e.timestamp', 'ASC')
                ->getQuery()
                ->getResult();

            if (!empty($entities)) {
                $rows = $entities; // process entities in the loop below
            }
        }

        // Groups of usernames per month (set semantics via associative arrays)
        $usersByMonth = [];
        foreach ($rows as $row) {
            // Support both scalar/array results and full entity objects
            if (is_array($row)) {
                $ts = $row['ts'] ?? $row['timestamp'] ?? null;
                $username = isset($row['username']) ? (string)$row['username'] : null;
            } elseif ($row instanceof \App\Entity\EmailSent) {
                $ts = $row->getTimestamp();
                $username = $row->getUsername() ? $row->getUsername()->getValue() : null;
            } else {
                // Unknown row type
                continue;
            }

            if (!$ts || $username === null) {
                continue;
            }

            if ($ts instanceof \DateTimeInterface) {
                $monthKey = $ts->format('Y-m');
            } else {
                try {
                    $dt = new \DateTime($ts);
                    $monthKey = $dt->format('Y-m');
                } catch (\Exception $e) {
                    // Skip rows with invalid timestamps
                    continue;
                }
            }

            $usersByMonth[$monthKey][$username] = true;
        }

        // Convert to counts per month
        $resultsByMonth = [];
        foreach ($usersByMonth as $monthKey => $users) {
            $resultsByMonth[$monthKey] = count($users);
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

    /**
     * Holt die Anzahl einzigartiger Benutzer pro Monat und Domain für die letzten 6 Monate
     *
     * Gibt ein Array zurück, das für jeden der letzten 6 Monate die Anzahl
     * der einzigartigen Benutzer pro E-Mail-Domain enthält. Dies ermöglicht die
     * Identifizierung, welche Tochterfirma (basierend auf der Domain) Tickets eröffnet hat.
     *
     * @return array Array mit monatlichen Domain-Statistiken [['month' => 'YYYY-MM', 'domains' => ['domain.com' => count, ...]], ...]
     */
    public function getMonthlyUserStatisticsByDomain(): array
    {
        // Berechne das Datum vor 5 Monaten (zusammen mit dem aktuellen Monat = 6 Monate)
        $fiveMonthsAgo = (new \DateTime())->modify('-5 months first day of this month')->setTime(0, 0, 0);

        // Hole Rohdaten (timestamp, username, email) und berechne die monatlichen Unique-Counts in PHP.
        $qb = $this->createQueryBuilder('e')
            ->select('e.timestamp as ts, e.username as username, e.email as email')
            ->where('e.timestamp >= :fiveMonthsAgo')
            ->andWhere('e.status = :status')
            ->setParameter('fiveMonthsAgo', $fiveMonthsAgo)
            ->setParameter('status', \App\ValueObject\EmailStatus::sent()->getValue())
            ->orderBy('e.timestamp', 'ASC');

        // Use array hydration to get consistent scalar arrays
        $rows = $qb->getQuery()->getArrayResult();

        // Fallback: if arrayResult is empty for some DB/Doctrine setups, try entity hydration
        if (empty($rows)) {
            $entities = $this->createQueryBuilder('e')
                ->select('e')
                ->where('e.timestamp >= :fiveMonthsAgo')
                ->andWhere('e.status = :status')
                ->setParameter('fiveMonthsAgo', $fiveMonthsAgo)
                ->setParameter('status', 'sent')
                ->orderBy('e.timestamp', 'ASC')
                ->getQuery()
                ->getResult();

            if (!empty($entities)) {
                $rows = $entities; // process entities in the loop below
            }
        }

        // Groups of usernames per month and domain (set semantics via associative arrays)
        // Structure: $usersByMonthAndDomain['2024-01']['example.com']['user1'] = true
        $usersByMonthAndDomain = [];
        foreach ($rows as $row) {
            // Support both scalar/array results and full entity objects
            if (is_array($row)) {
                $ts = $row['ts'] ?? $row['timestamp'] ?? null;
                $username = isset($row['username']) ? (string)$row['username'] : null;
                $email = $row['email'] ?? null;
            } elseif ($row instanceof \App\Entity\EmailSent) {
                $ts = $row->getTimestamp();
                $username = $row->getUsername() ? $row->getUsername()->getValue() : null;
                $email = $row->getEmail();
            } else {
                // Unknown row type
                continue;
            }

            if (!$ts || $username === null || !$email) {
                continue;
            }

            // Extract domain from email
            $domain = null;
            if (is_string($email)) {
                // Email is stored as string in array result
                $atPos = strrpos($email, '@');
                if ($atPos !== false) {
                    $domain = substr($email, $atPos + 1);
                }
            } elseif ($email instanceof \App\ValueObject\EmailAddress) {
                // Email is an EmailAddress object
                $domain = $email->getDomain();
            }

            if (!$domain) {
                continue;
            }

            // Extract month from timestamp
            if ($ts instanceof \DateTimeInterface) {
                $monthKey = $ts->format('Y-m');
            } else {
                try {
                    $dt = new \DateTime($ts);
                    $monthKey = $dt->format('Y-m');
                } catch (\Exception $e) {
                    // Skip rows with invalid timestamps
                    continue;
                }
            }

            $usersByMonthAndDomain[$monthKey][$domain][$username] = true;
        }

        // Convert to counts per month and domain
        $resultsByMonth = [];
        foreach ($usersByMonthAndDomain as $monthKey => $domains) {
            $domainCounts = [];
            foreach ($domains as $domain => $users) {
                $domainCounts[$domain] = count($users);
            }
            $resultsByMonth[$monthKey] = $domainCounts;
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
                'domains' => $resultsByMonth[$monthKey] ?? [],
                'total_users' => array_sum($resultsByMonth[$monthKey] ?? [])
            ];
        }

        return $monthlyStats;
    }
}