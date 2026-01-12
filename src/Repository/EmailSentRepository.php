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

        // Versuche eine aggregierte Abfrage in der Datenbank zu machen
        try {
            $conn = $this->getEntityManager()->getConnection();

            // DATE_FORMAT und SUBSTRING_INDEX sind für MySQL/MariaDB verfügbar und effizient.
            // Wir zählen DISTINCT username pro (month, domain).
            $sql = <<<SQL
SELECT DATE_FORMAT(e.timestamp, '%Y-%m') AS month,
       LOWER(TRIM(SUBSTRING_INDEX(e.email, '@', -1))) AS domain,
       COUNT(DISTINCT e.username) AS users
FROM emails_sent e
WHERE e.timestamp >= :fiveMonthsAgo
  AND (
      e.status = :status OR
      e.status = :status_plain OR
      e.status LIKE :status_like
  )
  AND e.email LIKE '%@%'
GROUP BY month, domain
ORDER BY month ASC, users DESC, domain ASC
SQL;

            $stmt = $conn->prepare($sql);
            $stmt->bindValue('fiveMonthsAgo', $fiveMonthsAgo->format('Y-m-d H:i:s'));
            $stmt->bindValue('status', \App\ValueObject\EmailStatus::sent()->getValue());
            // Accept both the localized 'Versendet' and legacy 'sent' marker, and any variants starting with the localized value
            $stmt->bindValue('status_plain', 'sent');
            $stmt->bindValue('status_like', \App\ValueObject\EmailStatus::sent()->getValue() . '%');
            $rows = $stmt->executeQuery()->fetchAllAssociative();

            $resultsByMonth = [];
            foreach ($rows as $row) {
                $month = $row['month'] ?? null;
                $domain = $row['domain'] ?? null;
                $users = isset($row['users']) ? (int)$row['users'] : 0;

                if ($month && $domain) {
                    $resultsByMonth[$month][$domain] = $users;
                }
            }

            // Sortiere Domains pro Monat absteigend nach Benutzeranzahl
            foreach ($resultsByMonth as $monthKey => &$domainCounts) {
                arsort($domainCounts, SORT_NUMERIC);
            }
            unset($domainCounts);

        } catch (\Throwable $e) {
            // Falls die DB-Funktionalität nicht verfügbar ist (z. B. anderes RDBMS ohne DATE_FORMAT/SUBSTRING_INDEX),
            // falle zurück auf die PHP-basierte Aggregation (robustheit)

            // Hole Rohdaten (timestamp, username, email) und berechne die monatlichen Unique-Counts in PHP.
            $qb = $this->createQueryBuilder('e')
                ->select('e.timestamp as ts, e.username as username, e.email as email')
                ->where('e.timestamp >= :fiveMonthsAgo')
                ->andWhere('(e.status = :status OR e.status = :status_plain OR e.status LIKE :status_like)')
                ->setParameter('fiveMonthsAgo', $fiveMonthsAgo)
                ->setParameter('status', \App\ValueObject\EmailStatus::sent()->getValue())
                ->setParameter('status_plain', 'sent')
                ->setParameter('status_like', \App\ValueObject\EmailStatus::sent()->getValue() . '%')
                ->orderBy('e.timestamp', 'ASC');

            $rows = $qb->getQuery()->getArrayResult();

            if (empty($rows)) {
                $entities = $this->createQueryBuilder('e')
                    ->select('e')
                    ->where('e.timestamp >= :fiveMonthsAgo')
                    ->andWhere('(e.status = :status OR e.status = :status_plain OR e.status LIKE :status_like)')
                    ->setParameter('fiveMonthsAgo', $fiveMonthsAgo)
                    ->setParameter('status', \App\ValueObject\EmailStatus::sent()->getValue())
                    ->setParameter('status_plain', 'sent')
                    ->setParameter('status_like', \App\ValueObject\EmailStatus::sent()->getValue() . '%')
                    ->orderBy('e.timestamp', 'ASC')
                    ->getQuery()
                    ->getResult();

                if (!empty($entities)) {
                    $rows = $entities; // process entities in the loop below
                }
            }

            $usersByMonthAndDomain = [];
            foreach ($rows as $row) {
                if (is_array($row)) {
                    $ts = $row['ts'] ?? $row['timestamp'] ?? null;
                    $username = isset($row['username']) ? (string)$row['username'] : null;
                    $email = $row['email'] ?? null;
                } elseif ($row instanceof \App\Entity\EmailSent) {
                    $ts = $row->getTimestamp();
                    $username = $row->getUsername() ? $row->getUsername()->getValue() : null;
                    $email = $row->getEmail();
                } else {
                    continue;
                }

                if (!$ts || $username === null || !$email) {
                    continue;
                }

                // Extract domain from email
                $domain = null;
                if (is_string($email)) {
                    $atPos = strrpos($email, '@');
                    if ($atPos !== false) {
                        $domain = substr($email, $atPos + 1);
                    }
                } elseif ($email instanceof \App\ValueObject\EmailAddress) {
                    $domain = $email->getDomain();
                }

                if (!$domain) {
                    continue;
                }

                // Normalize domain: trim and lowercase to avoid duplicates by case/whitespace
                $domain = strtolower(trim($domain));

                // Basic validation: domain must contain a dot (skip invalid domains)
                if (strpos($domain, '.') === false) {
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

            $resultsByMonth = [];
            foreach ($usersByMonthAndDomain as $monthKey => $domains) {
                $domainCounts = [];
                foreach ($domains as $domain => $users) {
                    $domainCounts[$domain] = count($users);
                }

                // Sortiere Domains pro Monat absteigend nach Benutzeranzahl
                arsort($domainCounts, SORT_NUMERIC);

                $resultsByMonth[$monthKey] = $domainCounts;
            }
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

        // Aktuellsten Monat zuerst anzeigen
        return array_reverse($monthlyStats);
    }

    /**
     * Holt die Anzahl einzigartiger Tickets pro Monat und Domain für die letzten 6 Monate
     *
     * @return array Array mit monatlichen Domain-Statistiken [['month' => 'YYYY-MM', 'domains' => ['domain.com' => count, ...]], ...]
     */
    public function getMonthlyTicketStatisticsByDomain(): array
    {
        return $this->getMonthlyDomainStatistics('ticket_id', 'total_tickets');
    }

    /**
     * Gemeinsame Implementierung für monatliche Domain-Statistiken.
     *
     * @param string $distinctField "username" oder "ticket_id"
     * @param string $totalKey Namensschlüssel für die Gesamtanzahl (z.B. 'total_users' oder 'total_tickets')
     * @return array
     */
    private function getMonthlyDomainStatistics(string $distinctField, string $totalKey): array
    {
        if (!in_array($distinctField, ['username', 'ticket_id'], true)) {
            throw new \InvalidArgumentException('Ungültiges distinct field: ' . $distinctField);
        }

        $fiveMonthsAgo = (new \DateTime())->modify('-5 months first day of this month')->setTime(0, 0, 0);

        $resultsByMonth = [];

        try {
            $conn = $this->getEntityManager()->getConnection();
            $distinctSqlColumn = $distinctField === 'username' ? 'e.username' : 'e.ticket_id';
            $countAlias = 'val_count';

            $sql = <<<SQL
SELECT DATE_FORMAT(e.timestamp, '%Y-%m') AS month,
       LOWER(TRIM(SUBSTRING_INDEX(e.email, '@', -1))) AS domain,
       COUNT(DISTINCT {$distinctSqlColumn}) AS {$countAlias}
FROM emails_sent e
WHERE e.timestamp >= :fiveMonthsAgo
  AND (
      e.status = :status OR
      e.status = :status_plain OR
      e.status LIKE :status_like
  )
  AND e.email LIKE '%@%'
GROUP BY month, domain
ORDER BY month ASC, {$countAlias} DESC, domain ASC
SQL;

            $stmt = $conn->prepare($sql);
            $stmt->bindValue('fiveMonthsAgo', $fiveMonthsAgo->format('Y-m-d H:i:s'));
            $stmt->bindValue('status', \App\ValueObject\EmailStatus::sent()->getValue());
            $stmt->bindValue('status_plain', 'sent');
            $stmt->bindValue('status_like', \App\ValueObject\EmailStatus::sent()->getValue() . '%');
            $rows = $stmt->executeQuery()->fetchAllAssociative();

            foreach ($rows as $row) {
                $month = $row['month'] ?? null;
                $domain = $row['domain'] ?? null;
                $count = isset($row[$countAlias]) ? (int)$row[$countAlias] : 0;

                if ($month && $domain) {
                    $resultsByMonth[$month][$domain] = $count;
                }
            }

            // Sortiere Domains pro Monat absteigend nach Anzahl
            foreach ($resultsByMonth as $monthKey => &$domainCounts) {
                arsort($domainCounts, SORT_NUMERIC);
            }
            unset($domainCounts);

        } catch (\Throwable $e) {
            // Fallback auf PHP-basierte Aggregation
            $qb = $this->createQueryBuilder('e')
                ->select('e.timestamp as ts, ' . ($distinctField === 'username' ? 'e.username as distinct_value' : 'e.ticketId as distinct_value') . ', e.email as email')
                ->where('e.timestamp >= :fiveMonthsAgo')
                ->andWhere('(e.status = :status OR e.status = :status_plain OR e.status LIKE :status_like)')
                ->setParameter('fiveMonthsAgo', $fiveMonthsAgo)
                ->setParameter('status', \App\ValueObject\EmailStatus::sent()->getValue())
                ->setParameter('status_plain', 'sent')
                ->setParameter('status_like', \App\ValueObject\EmailStatus::sent()->getValue() . '%')
                ->orderBy('e.timestamp', 'ASC');

            $rows = $qb->getQuery()->getArrayResult();

            if (empty($rows)) {
                $entities = $this->createQueryBuilder('e')
                    ->select('e')
                    ->where('e.timestamp >= :fiveMonthsAgo')
                    ->andWhere('(e.status = :status OR e.status = :status_plain OR e.status LIKE :status_like)')
                    ->setParameter('fiveMonthsAgo', $fiveMonthsAgo)
                    ->setParameter('status', \App\ValueObject\EmailStatus::sent()->getValue())
                    ->setParameter('status_plain', 'sent')
                    ->setParameter('status_like', \App\ValueObject\EmailStatus::sent()->getValue() . '%')
                    ->orderBy('e.timestamp', 'ASC')
                    ->getQuery()
                    ->getResult();

                if (!empty($entities)) {
                    $rows = $entities;
                }
            }

            $valuesByMonthAndDomain = [];
            foreach ($rows as $row) {
                if (is_array($row)) {
                    $ts = $row['ts'] ?? $row['timestamp'] ?? null;
                    $distinctValue = $row['distinct_value'] ?? null;
                    $email = $row['email'] ?? null;
                } elseif ($row instanceof \App\Entity\EmailSent) {
                    $ts = $row->getTimestamp();
                    $distinctValue = $distinctField === 'username' ? ($row->getUsername() ? $row->getUsername()->getValue() : null) : ($row->getTicketId() ? $row->getTicketId()->getValue() : null);
                    $email = $row->getEmail();
                } else {
                    continue;
                }

                if (!$ts || $distinctValue === null || !$email) {
                    continue;
                }

                // Extract domain from email
                $domain = null;
                if (is_string($email)) {
                    $atPos = strrpos($email, '@');
                    if ($atPos !== false) {
                        $domain = substr($email, $atPos + 1);
                    }
                } elseif ($email instanceof \App\ValueObject\EmailAddress) {
                    $domain = $email->getDomain();
                }

                if (!$domain) {
                    continue;
                }

                // Normalize domain and validate
                $domain = strtolower(trim($domain));
                if (strpos($domain, '.') === false) {
                    continue;
                }

                // Extract month
                if ($ts instanceof \DateTimeInterface) {
                    $monthKey = $ts->format('Y-m');
                } else {
                    try {
                        $dt = new \DateTime($ts);
                        $monthKey = $dt->format('Y-m');
                    } catch (\Exception $ex) {
                        continue;
                    }
                }

                // Normalize distinct value to a string key (handles ValueObjects)
                $distinctKey = $this->normalizeDistinctValue($distinctValue);
                if ($distinctKey === null) {
                    // If we cannot convert the distinct value to a string, skip this row
                    continue;
                }

                $valuesByMonthAndDomain[$monthKey][$domain][$distinctKey] = true;
            }

            $resultsByMonth = [];
            foreach ($valuesByMonthAndDomain as $monthKey => $domains) {
                $domainCounts = [];
                foreach ($domains as $domain => $values) {
                    $domainCounts[$domain] = count($values);
                }

                arsort($domainCounts, SORT_NUMERIC);
                $resultsByMonth[$monthKey] = $domainCounts;
            }
        }

        // Erstelle ein vollständiges Array für die letzten 6 Monate (auch Monate ohne Daten)
        $monthlyStats = [];
        $currentDate = new \DateTime();
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = clone $currentDate;
            $monthDate->modify("-$i months");
            $monthKey = $monthDate->format('Y-m');

            $monthlyStats[] = [
                'month' => $monthKey,
                'domains' => $resultsByMonth[$monthKey] ?? [],
                $totalKey => array_sum($resultsByMonth[$monthKey] ?? [])
            ];
        }

        return array_reverse($monthlyStats);
    }

    /**
     * Gibt die monatlichen Benutzerstatistiken als DTOs zurück
     *
     * @return \App\Dto\MonthlyDomainStatistic[]
     */
    public function getMonthlyUserStatisticsByDomainDto(): array
    {
        return $this->mapToDtos($this->getMonthlyDomainStatistics('username', 'total_users'), 'total_users');
    }

    /**
     * Gibt die monatlichen Ticketstatistiken als DTOs zurück
     *
     * @return \App\Dto\MonthlyDomainStatistic[]
     */
    public function getMonthlyTicketStatisticsByDomainDto(): array
    {
        return $this->mapToDtos($this->getMonthlyDomainStatistics('ticket_id', 'total_tickets'), 'total_tickets');
    }

    /**
     * Mappt das interne Array-Format zu DTOs
     *
     * @param array $monthlyStats
     * @param string $totalKey
     * @return \App\Dto\MonthlyDomainStatistic[]
     */
    private function mapToDtos(array $monthlyStats, string $totalKey): array
    {
        $dtos = [];
        foreach ($monthlyStats as $stat) {
            $month = $stat['month'] ?? '';
            $domains = [];
            foreach ($stat['domains'] ?? [] as $domain => $count) {
                $domains[] = new \App\Dto\DomainCount($domain, (int)$count);
            }
            $total = isset($stat[$totalKey]) ? (int)$stat[$totalKey] : array_sum($stat['domains'] ?? []);
            $dtos[] = new \App\Dto\MonthlyDomainStatistic($month, $domains, $total);
        }
        return $dtos;
    }

    /**
     * Normalisiert einen distinct-Wert (Username oder TicketId) zu einem String-Key.
     * Unterstützt Strings, skalare Werte, ValueObjects mit getValue() oder Objekte mit __toString().
     *
     * @param mixed $value
     * @return string|null Der String-Key oder null, wenn nicht konvertierbar
     */
    private function normalizeDistinctValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_object($value)) {
            if (method_exists($value, 'getValue')) {
                $v = $value->getValue();
                return is_scalar($v) ? (string)$v : null;
            }

            if (method_exists($value, '__toString')) {
                return (string)$value;
            }

            return null;
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        return null;
    }
}