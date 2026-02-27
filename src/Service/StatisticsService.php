<?php
declare(strict_types=1);

namespace App\Service;

use App\Dto\MonthlyDomainStatistic;
use App\Dto\DomainCount;
use App\Repository\EmailSentRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class StatisticsService
{
    private const CACHE_KEY_USER_STATS = 'statistics.monthly_user_by_domain';
    private const CACHE_KEY_TICKET_STATS = 'statistics.monthly_ticket_by_domain';
    private const CACHE_TTL = 172800; // 48 hours

    public function __construct(
        private readonly EmailSentRepository $emailSentRepository,
        private readonly \App\Service\ClockInterface $clock,
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * Liefert monatliche Benutzerstatistiken als DTOs
     * 
     * Diese Methode ruft die Benutzerstatistiken der letzten N Monate ab und cached sie
     * für bessere Performance. Der Cache hat eine TTL von 48 Stunden.
     *
     * @param int $months Anzahl der Monate (Standard: 6)
     * @return MonthlyDomainStatistic[] Array von MonthlyDomainStatistic DTOs, sortiert vom neuesten zum ältesten Monat
     */
    public function getMonthlyUserStatisticsByDomain(int $months = 6): array
    {
        $cacheKey = self::CACHE_KEY_USER_STATS . '_' . $months;
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($months) {
            $item->expiresAfter(self::CACHE_TTL);
            
            $range = \App\ValueObject\MonthRange::lastMonths($months, $this->clock);
            $since = $range->start();
            
            // Get domain statistics
            $rows = $this->emailSentRepository->getMonthlyDomainCountsRows('username', $since);

            // Get new users by month
            $newUsersByMonth = $this->emailSentRepository->getNewUsersByMonth($since);

            // aggregate rows into month->domain->count map
            $map = [];
            foreach ($rows as $r) {
                $m = $r['month'];
                $d = $r['domain'];
                $c = $r['count'];
                $map[$m][$d] = $c;
            }

            // build full months array and map to DTOs
            $monthlyStats = [];
            foreach ($range->months() as $monthKey) {
                $domains = $map[$monthKey] ?? [];
                // ensure domains sorted desc
                arsort($domains, SORT_NUMERIC);
                $newUsers = $newUsersByMonth[$monthKey] ?? 0;
                $monthlyStats[] = [
                    'month' => $monthKey, 
                    'domains' => $domains, 
                    'total_users' => array_sum($domains),
                    'new_users' => $newUsers
                ];
            }

            // months() returns oldest->newest; for UI we want newest first
            $monthlyStats = array_reverse($monthlyStats);

            return $this->mapToDtos($monthlyStats, 'total_users');
        });
    }

    /**
     * Liefert monatliche Ticketstatistiken als DTOs
     * 
     * Diese Methode ruft die Ticketstatistiken der letzten N Monate ab und cached sie
     * für bessere Performance. Der Cache hat eine TTL von 48 Stunden.
     *
     * @param int $months Anzahl der Monate (Standard: 6)
     * @return MonthlyDomainStatistic[] Array von MonthlyDomainStatistic DTOs, sortiert vom neuesten zum ältesten Monat
     */
    public function getMonthlyTicketStatisticsByDomain(int $months = 6): array
    {
        $cacheKey = self::CACHE_KEY_TICKET_STATS . '_' . $months;
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($months) {
            $item->expiresAfter(self::CACHE_TTL);
            
            return $this->getMonthlyStatisticsByDomain('ticket_id', 'total_tickets', $months);
        });
    }

    /**
     * Löscht den Cache nur für den aktuellen Monat
     * 
     * Diese Methode wird beim Versand von E-Mails (CSV-Upload) aufgerufen, um sicherzustellen,
     * dass die Statistiken für den aktuellen Monat aktualisiert werden. Da der aktuelle Monat
     * immer in den Statistiken enthalten ist, wird nur der Standard-Cache (6 Monate) gelöscht,
     * der am häufigsten verwendet wird. Andere Cache-Einträge (z.B. für 1, 3, 12 Monate) bleiben
     * erhalten, um die Performance zu optimieren.
     * 
     * @return void
     */
    public function clearCurrentMonthCache(): void
    {
        // Clear only the default 6-month cache which is used by the dashboard
        // This affects the current month's statistics while preserving other cached ranges
        $this->cache->delete(self::CACHE_KEY_USER_STATS . '_6');
        $this->cache->delete(self::CACHE_KEY_TICKET_STATS . '_6');
    }

    /**
     * Löscht den gesamten Statistik-Cache
     * 
     * Diese Methode löscht alle Cache-Einträge für Statistiken (1-12 Monate).
     * Sie wird aufgerufen, wenn der Benutzer manuell über die Dashboard-UI den
     * "Cache löschen" Button klickt. Im Gegensatz zu clearCurrentMonthCache()
     * werden hier alle möglichen Cache-Varianten gelöscht, um eine vollständige
     * Aktualisierung zu gewährleisten.
     * 
     * Cache-Einträge haben eine TTL von 48 Stunden und verfallen automatisch,
     * aber diese Methode ermöglicht eine sofortige manuelle Aktualisierung.
     * 
     * @return void
     */
    public function clearCache(): void
    {
        // Clear cache keys for months 1-12 (covers default 6 months and extended ranges)
        for ($months = 1; $months <= 12; $months++) {
            $this->cache->delete(self::CACHE_KEY_USER_STATS . '_' . $months);
            $this->cache->delete(self::CACHE_KEY_TICKET_STATS . '_' . $months);
        }
    }

    /**
     * Generische Methode für monatliche Statistiken nach Domain
     *
     * @param string $distinctField 'username' oder 'ticket_id'
     * @param string $totalKey 'total_users' oder 'total_tickets'
     * @param int $months Anzahl der Monate
     * @return MonthlyDomainStatistic[]
     */
    private function getMonthlyStatisticsByDomain(string $distinctField, string $totalKey, int $months): array
    {
        $range = \App\ValueObject\MonthRange::lastMonths($months, $this->clock);
        $since = $range->start();
        $rows = $this->emailSentRepository->getMonthlyDomainCountsRows($distinctField, $since);

        // aggregate rows into month->domain->count map
        $map = [];
        foreach ($rows as $r) {
            $m = $r['month'];
            $d = $r['domain'];
            $c = $r['count'];
            $map[$m][$d] = $c;
        }

        // build full months array and map to DTOs
        $monthlyStats = [];
        foreach ($range->months() as $monthKey) {
            $domains = $map[$monthKey] ?? [];
            // ensure domains sorted desc
            arsort($domains, SORT_NUMERIC);
            $monthlyStats[] = ['month' => $monthKey, 'domains' => $domains, $totalKey => array_sum($domains)];
        }

        // months() returns oldest->newest; for UI we want newest first
        $monthlyStats = array_reverse($monthlyStats);

        return $this->mapToDtos($monthlyStats, $totalKey);
    }

    /**
     * Mappt das interne Array-Format zu DTOs
     *
     * @param array $monthlyStats
     * @param string $totalKey
     * @return MonthlyDomainStatistic[]
     */
    private function mapToDtos(array $monthlyStats, string $totalKey): array
    {
        $dtos = [];
        foreach ($monthlyStats as $stat) {
            $month = $stat['month'] ?? '';
            $total = isset($stat[$totalKey]) ? (int)$stat[$totalKey] : array_sum($stat['domains'] ?? []);
            $domains = [];
            foreach ($stat['domains'] ?? [] as $domain => $count) {
                $domains[] = new DomainCount($domain, (int)$count, $total);
            }
            $newUsers = isset($stat['new_users']) ? (int)$stat['new_users'] : 0;
            $dtos[] = new MonthlyDomainStatistic($month, $domains, $total, $newUsers);
        }
        return $dtos;
    }
}
