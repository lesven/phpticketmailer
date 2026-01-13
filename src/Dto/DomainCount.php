<?php

namespace App\Dto;

final class DomainCount
{
    private string $domain;
    private int $count;
    private int $total;

    public function __construct(string $domain, int $count, int $total = 0)
    {
        $this->domain = $domain;
        $this->count = $count;
        $this->total = $total;
    }

    public function domain(): string
    {
        return $this->domain;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function percentage(): string
    {
        if ($this->total === 0) {
            return '0';
        }
        $percentage = ($this->count / $this->total) * 100;
        return number_format($percentage, 1, '.', '');
    }
}
