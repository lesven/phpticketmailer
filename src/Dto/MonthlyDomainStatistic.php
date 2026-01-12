<?php

namespace App\Dto;

/**
 * Represents aggregated domain statistics for a single month
 */
final class MonthlyDomainStatistic
{
    private string $month; // format YYYY-MM
    /** @var DomainCount[] */
    private array $domains;
    private int $total;

    /**
     * @param string $month Format 'YYYY-MM'
     * @param DomainCount[] $domains
     * @param int $total
     */
    public function __construct(string $month, array $domains, int $total)
    {
        $this->month = $month;
        $this->domains = $domains;
        $this->total = $total;
    }

    public function month(): string
    {
        return $this->month;
    }

    /** @return DomainCount[] */
    public function domains(): array
    {
        return $this->domains;
    }

    public function total(): int
    {
        return $this->total;
    }
}
