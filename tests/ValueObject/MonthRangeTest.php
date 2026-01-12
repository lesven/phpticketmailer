<?php

namespace App\Tests\ValueObject;

use App\ValueObject\MonthRange;
use App\Service\ClockInterface;
use PHPUnit\Framework\TestCase;

class MonthRangeTest extends TestCase
{
    public function testLastMonthsProducesCorrectStartAndMonths(): void
    {
        $clock = $this->createMock(ClockInterface::class);
        // Simulate now = 2026-06-15
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-06-15'));

        $range = MonthRange::lastMonths(6, $clock);
        $this->assertEquals('2026-01-01 00:00:00', $range->start()->format('Y-m-d H:i:s'));

        $months = $range->months();
        $this->assertSame(['2026-01','2026-02','2026-03','2026-04','2026-05','2026-06'], $months);
    }
}
