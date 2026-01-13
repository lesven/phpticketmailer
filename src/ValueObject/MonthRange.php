<?php

namespace App\ValueObject;

use App\Service\ClockInterface;

final class MonthRange
{
    private \DateTimeImmutable $start;
    private int $months;

    private function __construct(\DateTimeImmutable $start, int $months)
    {
        $this->start = $start;
        $this->months = $months;
    }

    public static function lastMonths(int $n, ClockInterface $clock): self
    {
        if ($n <= 0) {
            throw new \InvalidArgumentException('n must be > 0');
        }

        // Compute the first day of the month n-1 months ago
        $now = $clock->now();
        $start = $now->modify(sprintf('-%d months first day of this month', $n - 1))->setTime(0, 0, 0);
        return new self($start, $n);
    }

    public function start(): \DateTimeImmutable
    {
        return $this->start;
    }

    /** @return string[] Month keys in format YYYY-MM starting from start */
    public function months(): array
    {
        $months = [];
        for ($i = 0; $i < $this->months; $i++) {
            $d = $this->start->modify("+{$i} months");
            $months[] = $d->format('Y-m');
        }
        return $months;
    }
}
