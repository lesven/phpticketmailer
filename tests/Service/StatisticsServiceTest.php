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

        $repo->method('getMonthlyDomainCountsRows')
            ->willReturnCallback(function($distinct, $since) use ($raw) {
                // assert we received a DateTimeImmutable since parameter
                $this->assertInstanceOf(\DateTimeImmutable::class, $since);
                // convert raw monthly map to rows
                $rows = [];
                foreach ($raw as $stat) {
                    foreach ($stat['domains'] as $domain => $count) {
                        $rows[] = ['month' => $stat['month'], 'domain' => $domain, 'count' => $count];
                    }
                }
                return $rows;
            });

        $clock = $this->createMock(\App\Service\ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-15'));

        $service = new StatisticsService($repo, $clock);
        $dtos = $service->getMonthlyUserStatisticsByDomain();

        $this->assertIsArray($dtos);
        // Default months=6 -> expect 6 DTO entries
        $this->assertCount(6, $dtos);

        // Neuerdings soll der neuste Monat oben stehen -> erster Eintrag ist das aktuelle Monat
        $this->assertEquals($clock->now()->format('Y-m'), $dtos[0]->month(), 'Expected newest month first');

        // Find DTO for 2026-01
        $found = null;
        foreach ($dtos as $dto) {
            if ($dto->month() === '2026-01') {
                $found = $dto;
                break;
            }
        }
        $this->assertNotNull($found, 'Expected a DTO for month 2026-01');
        $this->assertInstanceOf(MonthlyDomainStatistic::class, $found);
        $domains = $found->domains();
        $this->assertCount(2, $domains);
        $map = [];
        foreach ($domains as $d) {
            $map[$d->domain()] = $d->count();
        }
        $this->assertEquals(2, $map['example.com']);
        $this->assertEquals(1, $map['other.com']);
    }
}
