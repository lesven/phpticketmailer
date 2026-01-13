<?php

namespace App\Service;

use App\Dto\MonthlyDomainStatistic;
use App\Dto\DomainCount;
use App\Repository\EmailSentRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class StatisticsService
{
    private const CACHE_KEY_USER_STATS = 'statistics.monthly_user_by_domain';
    private const CACHE_KEY_TICKET_STATS = 'statistics.monthly_ticket_by_domain';
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private readonly EmailSentRepository $emailSentRepository, 
        private readonly \App\Service\ClockInterface $clock,
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * Liefert monatliche Benutzerstatistiken als DTOs
     *
     * @return MonthlyDomainStatistic[]
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
     * @return MonthlyDomainStatistic[]
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
     * Löscht den Statistik-Cache
     * 
     * Clears cache entries for months 1-12, which covers all typical use cases.
     * The default parameter in the getter methods is 6 months, and there's no
     * UI to change this value. Cache entries with TTL of 1 hour expire automatically.
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
