<?php

namespace App\Tests\Dto;

use App\Dto\DomainCount;
use App\Dto\MonthlyDomainStatistic;
use PHPUnit\Framework\TestCase;

class MonthlyDomainStatisticTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $domains = [
            new DomainCount('example.com', 10, 20),
            new DomainCount('other.com', 10, 20),
        ];

        $statistic = new MonthlyDomainStatistic('2026-01', $domains, 20, 5);

        $this->assertEquals('2026-01', $statistic->month());
        $this->assertSame($domains, $statistic->domains());
        $this->assertEquals(20, $statistic->total());
        $this->assertEquals(5, $statistic->newUsers());
    }

    public function testDefaultNewUsersIsZero(): void
    {
        $statistic = new MonthlyDomainStatistic('2026-02', [], 0);

        $this->assertEquals(0, $statistic->newUsers());
    }

    public function testEmptyDomains(): void
    {
        $statistic = new MonthlyDomainStatistic('2026-03', [], 0, 0);

        $this->assertIsArray($statistic->domains());
        $this->assertEmpty($statistic->domains());
        $this->assertEquals(0, $statistic->total());
    }

    public function testMonthFormat(): void
    {
        $statistic = new MonthlyDomainStatistic('2025-12', [], 0);

        $this->assertEquals('2025-12', $statistic->month());
    }

    public function testDomainsAreReturnedAsProvided(): void
    {
        $domain1 = new DomainCount('company.de', 30, 100);
        $domain2 = new DomainCount('firm.com', 70, 100);

        $statistic = new MonthlyDomainStatistic('2026-01', [$domain1, $domain2], 100, 10);

        $domains = $statistic->domains();
        $this->assertCount(2, $domains);
        $this->assertSame($domain1, $domains[0]);
        $this->assertSame($domain2, $domains[1]);
    }

    public function testTotalReflectsAllEmails(): void
    {
        $statistic = new MonthlyDomainStatistic('2026-01', [], 500, 50);

        $this->assertEquals(500, $statistic->total());
    }

    public function testNewUsersCanBeZero(): void
    {
        $statistic = new MonthlyDomainStatistic('2026-04', [], 10, 0);

        $this->assertEquals(0, $statistic->newUsers());
    }

    public function testNewUsersLargerThanTotal(): void
    {
        // Edge case: constructor does not validate this constraint
        $statistic = new MonthlyDomainStatistic('2026-05', [], 5, 10);

        $this->assertEquals(5, $statistic->total());
        $this->assertEquals(10, $statistic->newUsers());
    }
}
