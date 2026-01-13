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

    public function testLastMonthsWithSingleMonth(): void
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-03-15'));

        $range = MonthRange::lastMonths(1, $clock);
        $this->assertEquals('2026-03-01 00:00:00', $range->start()->format('Y-m-d H:i:s'));

        $months = $range->months();
        $this->assertSame(['2026-03'], $months);
    }

    public function testLastMonthsAcrossYearBoundary(): void
    {
        $clock = $this->createMock(ClockInterface::class);
        // Simulate now = January 2026
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-15'));

        $range = MonthRange::lastMonths(3, $clock);
        $this->assertEquals('2025-11-01 00:00:00', $range->start()->format('Y-m-d H:i:s'));

        $months = $range->months();
        $this->assertSame(['2025-11','2025-12','2026-01'], $months);
    }

    public function testLastMonthsWithFullYear(): void
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-12-31'));

        $range = MonthRange::lastMonths(12, $clock);
        $this->assertEquals('2026-01-01 00:00:00', $range->start()->format('Y-m-d H:i:s'));

        $months = $range->months();
        $this->assertCount(12, $months);
        $this->assertEquals('2026-01', $months[0]);
        $this->assertEquals('2026-12', $months[11]);
    }

    public function testLastMonthsOnFirstDayOfMonth(): void
    {
        $clock = $this->createMock(ClockInterface::class);
        // Test on the first day of the month
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-06-01'));

        $range = MonthRange::lastMonths(3, $clock);
        $this->assertEquals('2026-04-01 00:00:00', $range->start()->format('Y-m-d H:i:s'));

        $months = $range->months();
        $this->assertSame(['2026-04','2026-05','2026-06'], $months);
    }

    public function testLastMonthsOnLastDayOfMonth(): void
    {
        $clock = $this->createMock(ClockInterface::class);
        // Test on the last day of the month
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-06-30'));

        $range = MonthRange::lastMonths(3, $clock);
        $this->assertEquals('2026-04-01 00:00:00', $range->start()->format('Y-m-d H:i:s'));

        $months = $range->months();
        $this->assertSame(['2026-04','2026-05','2026-06'], $months);
    }

    public function testLastMonthsThrowsExceptionForZeroMonths(): void
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-06-15'));

        $this->expectException(\InvalidArgumentException::class);
        MonthRange::lastMonths(0, $clock);
    }

    public function testLastMonthsThrowsExceptionForNegativeMonths(): void
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-06-15'));

        $this->expectException(\InvalidArgumentException::class);
        MonthRange::lastMonths(-1, $clock);
    }
}
