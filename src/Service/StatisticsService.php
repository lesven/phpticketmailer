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
        $range = \App\ValueObject\MonthRange::lastMonths($months, $this->clock);
        $since = $range->start();
        $rows = $this->emailSentRepository->getMonthlyDomainCountsRows('username', $since);

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
            $monthlyStats[] = ['month' => $monthKey, 'domains' => $domains, 'total_users' => array_sum($domains)];
        }

        return $this->mapToDtos($monthlyStats, 'total_users');
    }

    /**
     * Liefert monatliche Ticketstatistiken als DTOs
     *
     * @return MonthlyDomainStatistic[]
     */
    public function getMonthlyTicketStatisticsByDomain(int $months = 6): array
    {
        $range = \App\ValueObject\MonthRange::lastMonths($months, $this->clock);
        $since = $range->start();
        $rows = $this->emailSentRepository->getMonthlyDomainCountsRows('ticket_id', $since);

        $map = [];
        foreach ($rows as $r) {
            $m = $r['month'];
            $d = $r['domain'];
            $c = $r['count'];
            $map[$m][$d] = $c;
        }

        $monthlyStats = [];
        foreach ($range->months() as $monthKey) {
            $domains = $map[$monthKey] ?? [];
            arsort($domains, SORT_NUMERIC);
            $monthlyStats[] = ['month' => $monthKey, 'domains' => $domains, 'total_tickets' => array_sum($domains)];
        }

        return $this->mapToDtos($monthlyStats, 'total_tickets');
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
