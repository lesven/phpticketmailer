<?php

namespace App\Tests\Dto;

use App\Dto\DomainCount;
use PHPUnit\Framework\TestCase;

class DomainCountTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $domainCount = new DomainCount('example.com', 10, 50);

        $this->assertEquals('example.com', $domainCount->domain());
        $this->assertEquals(10, $domainCount->count());
    }

    public function testPercentageCalculation(): void
    {
        $domainCount = new DomainCount('example.com', 25, 100);

        $this->assertEquals('25.0', $domainCount->percentage());
    }

    public function testPercentageCalculationWithDecimals(): void
    {
        $domainCount = new DomainCount('example.com', 33, 100);

        $this->assertEquals('33.0', $domainCount->percentage());
    }

    public function testPercentageCalculationWithOneDecimal(): void
    {
        $domainCount = new DomainCount('example.com', 1, 3);

        // 1/3 = 0.333... -> 33.3%
        $this->assertEquals('33.3', $domainCount->percentage());
    }

    public function testPercentageCalculationWhenTotalIsZero(): void
    {
        $domainCount = new DomainCount('example.com', 0, 0);

        $this->assertEquals('0', $domainCount->percentage());
    }

    public function testPercentageCalculationWithoutTotal(): void
    {
        // Test backward compatibility when total is not provided
        $domainCount = new DomainCount('example.com', 10);

        $this->assertEquals('0', $domainCount->percentage());
    }

    public function testPercentageCalculationFullAmount(): void
    {
        $domainCount = new DomainCount('example.com', 100, 100);

        $this->assertEquals('100.0', $domainCount->percentage());
    }
}
