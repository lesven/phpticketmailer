<?php

namespace App\Service;

use App\Dto\MonthlyDomainStatistic;
use App\Dto\DomainCount;
use App\Repository\EmailSentRepository;

class StatisticsService
{
    public function __construct(private readonly EmailSentRepository $emailSentRepository, private readonly \App\Service\ClockInterface $clock)
    {
    }

    /**
     * Liefert monatliche Benutzerstatistiken als DTOs
     *
     * @return MonthlyDomainStatistic[]
     */
    public function getMonthlyUserStatisticsByDomain(int $months = 6): array
    {
        return $this->getMonthlyStatisticsByDomain('username', 'total_users', $months);
    }

    /**
     * Liefert monatliche Ticketstatistiken als DTOs
     *
     * @return MonthlyDomainStatistic[]
     */
    public function getMonthlyTicketStatisticsByDomain(int $months = 6): array
    {
        return $this->getMonthlyStatisticsByDomain('ticket_id', 'total_tickets', $months);
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
            $domains = [];
            foreach ($stat['domains'] ?? [] as $domain => $count) {
                $domains[] = new DomainCount($domain, (int)$count);
            }
            $total = isset($stat[$totalKey]) ? (int)$stat[$totalKey] : array_sum($stat['domains'] ?? []);
            $dtos[] = new MonthlyDomainStatistic($month, $domains, $total);
        }
        return $dtos;
    }
}
