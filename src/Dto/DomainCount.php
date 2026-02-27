<?php
declare(strict_types=1);

namespace App\Dto;

/**
 * Data transfer object representing counts grouped by email domain.
 *
 * Holds the domain name, the number of occurrences for that domain and
 * the overall total which is used to calculate the percentage share.
 */
final class DomainCount
{
    /**
     * Domain name (e.g. example.com).
     *
     * @var string
     */
    private string $domain;

    /**
     * Number of occurrences for the domain.
     *
     * @var int
     */
    private int $count;

    /**
     * Total number of occurrences across all domains used for percentage calculation.
     *
     * @var int
     */
    private int $total;

    /**
     * DomainCount constructor.
     *
     * @param string $domain Domain name (e.g. example.com)
     * @param int $count Number of occurrences for this domain
     * @param int $total Total number of occurrences across all domains (defaults to 0)
     */
    public function __construct(string $domain, int $count, int $total = 0)
    {
        $this->domain = $domain;
        $this->count = $count;
        $this->total = $total;
    }

    /**
     * Returns the domain name.
     *
     * @return string Domain name
     */
    public function domain(): string
    {
        return $this->domain;
    }

    /**
     * Returns the occurrence count for this domain.
     *
     * @return int Occurrence count
     */
    public function count(): int
    {
        return $this->count;
    }

    /**
     * Returns the percentage share of this domain as a formatted string with one decimal.
     *
     * If the total is zero the method returns the string "0".
     *
     * @return string Percentage formatted with one decimal point (e.g. "12.3") or "0" when total is 0
     */
    public function percentage(): string
    {
        if ($this->total === 0) {
            return '0';
        }
        $percentage = ($this->count / $this->total) * 100;
        return number_format($percentage, 1, '.', '');
    }
}
