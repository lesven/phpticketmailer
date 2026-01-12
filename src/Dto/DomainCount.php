<?php

namespace App\Dto;

final class DomainCount
{
    private string $domain;
    private int $count;

    public function __construct(string $domain, int $count)
    {
        $this->domain = $domain;
        $this->count = $count;
    }

    public function domain(): string
    {
        return $this->domain;
    }

    public function count(): int
    {
        return $this->count;
    }
}
