<?php

namespace App\Service;

interface ClockInterface
{
    public function now(): \DateTimeImmutable;
}
