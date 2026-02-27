<?php
declare(strict_types=1);

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
    private int $newUsers;

    /**
     * @param string $month Format 'YYYY-MM'
     * @param DomainCount[] $domains
     * @param int $total
     * @param int $newUsers Number of users who received their first email in this month
     */
    public function __construct(string $month, array $domains, int $total, int $newUsers = 0)
    {
        $this->month = $month;
        $this->domains = $domains;
        $this->total = $total;
        $this->newUsers = $newUsers;
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

    public function newUsers(): int
    {
        return $this->newUsers;
    }
}
