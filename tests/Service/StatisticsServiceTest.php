<?php

namespace App\Tests\Service;

use App\Service\StatisticsService;
use App\Repository\EmailSentRepository;
use PHPUnit\Framework\TestCase;
use App\Dto\MonthlyDomainStatistic;
use App\Dto\DomainCount;

class StatisticsServiceTest extends TestCase
{
    public function testMappingFromRawToDtos(): void
    {
        $repo = $this->createMock(EmailSentRepository::class);

        $raw = [
            ['month' => '2026-01', 'domains' => ['example.com' => 2, 'other.com' => 1], 'total_users' => 3],
            ['month' => '2026-02', 'domains' => [], 'total_users' => 0]
        ];

        $repo->method('getMonthlyDomainCountsRaw')
            ->with('username')
            ->willReturn($raw);

        $service = new StatisticsService($repo);
        $dtos = $service->getMonthlyUserStatisticsByDomain();

        $this->assertIsArray($dtos);
        $this->assertCount(2, $dtos);
        $this->assertInstanceOf(MonthlyDomainStatistic::class, $dtos[0]);
        $this->assertEquals('2026-01', $dtos[0]->month());
        $domains = $dtos[0]->domains();
        $this->assertCount(2, $domains);
        $map = [];
        foreach ($domains as $d) {
            $map[$d->domain()] = $d->count();
        }
        $this->assertEquals(2, $map['example.com']);
        $this->assertEquals(1, $map['other.com']);
    }
}
