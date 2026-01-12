<?php

namespace App\Service;

use App\Dto\MonthlyDomainStatistic;
use App\Dto\DomainCount;
use App\Repository\EmailSentRepository;

class StatisticsService
{
    public function __construct(private readonly EmailSentRepository $emailSentRepository)
    {
    }

    /**
     * Liefert monatliche Benutzerstatistiken als DTOs
     *
     * @return MonthlyDomainStatistic[]
     */
    public function getMonthlyUserStatisticsByDomain(): array
    {
        $raw = $this->emailSentRepository->getMonthlyDomainCountsRaw('username');
        return $this->mapToDtos($raw, 'total_users');
    }

    /**
     * Liefert monatliche Ticketstatistiken als DTOs
     *
     * @return MonthlyDomainStatistic[]
     */
    public function getMonthlyTicketStatisticsByDomain(): array
    {
        $raw = $this->emailSentRepository->getMonthlyDomainCountsRaw('ticket_id');
        return $this->mapToDtos($raw, 'total_tickets');
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
