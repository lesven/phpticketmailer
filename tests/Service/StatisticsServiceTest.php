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

        $repo->method('getNewUsersByMonth')
            ->willReturn(['2026-01' => 2, '2026-02' => 0]);

        $clock = $this->createMock(\App\Service\ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-15'));

        $cache = $this->createMock(\Symfony\Contracts\Cache\CacheInterface::class);
        $cache->method('get')
            ->willReturnCallback(function($key, $callback) {
                $item = $this->createMock(\Symfony\Contracts\Cache\ItemInterface::class);
                return $callback($item);
            });

        $service = new StatisticsService($repo, $clock, $cache);
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
        $this->assertEquals(2, $found->newUsers(), 'Expected 2 new users in 2026-01');
    }

    public function testGetMonthlyTicketStatisticsByDomain(): void
    {
        $repo = $this->createMock(EmailSentRepository::class);

        $raw = [
            ['month' => '2026-01', 'domains' => ['company-a.com' => 5, 'company-b.com' => 3], 'total_tickets' => 8],
            ['month' => '2026-02', 'domains' => [], 'total_tickets' => 0]
        ];

        $repo->method('getMonthlyDomainCountsRows')
            ->willReturnCallback(function($distinct, $since) use ($raw) {
                // assert we received a DateTimeImmutable since parameter
                $this->assertInstanceOf(\DateTimeImmutable::class, $since);
                $this->assertEquals('ticket_id', $distinct);
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

        $cache = $this->createMock(\Symfony\Contracts\Cache\CacheInterface::class);
        $cache->method('get')
            ->willReturnCallback(function($key, $callback) {
                $item = $this->createMock(\Symfony\Contracts\Cache\ItemInterface::class);
                return $callback($item);
            });

        $service = new StatisticsService($repo, $clock, $cache);
        $dtos = $service->getMonthlyTicketStatisticsByDomain();

        $this->assertIsArray($dtos);
        $this->assertCount(6, $dtos);

        // Verify newest month is first
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
        $this->assertEquals(5, $map['company-a.com']);
        $this->assertEquals(3, $map['company-b.com']);
        $this->assertEquals(8, $found->total());
    }

    public function testDomainCountObjectsHaveCorrectTotalForPercentageCalculation(): void
    {
        $repo = $this->createMock(EmailSentRepository::class);

        $raw = [
            ['month' => '2026-01', 'domains' => ['example.com' => 50, 'other.com' => 30, 'third.com' => 20], 'total_users' => 100],
        ];

        $repo->method('getMonthlyDomainCountsRows')
            ->willReturnCallback(function($distinct, $since) use ($raw) {
                $rows = [];
                foreach ($raw as $stat) {
                    foreach ($stat['domains'] as $domain => $count) {
                        $rows[] = ['month' => $stat['month'], 'domain' => $domain, 'count' => $count];
                    }
                }
                return $rows;
            });

        $repo->method('getNewUsersByMonth')
            ->willReturn(['2026-01' => 10]);

        $clock = $this->createMock(\App\Service\ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-15'));

        $cache = $this->createMock(\Symfony\Contracts\Cache\CacheInterface::class);
        $cache->method('get')
            ->willReturnCallback(function($key, $callback) {
                $item = $this->createMock(\Symfony\Contracts\Cache\ItemInterface::class);
                return $callback($item);
            });

        $service = new StatisticsService($repo, $clock, $cache);
        $dtos = $service->getMonthlyUserStatisticsByDomain();

        // Find DTO for 2026-01
        $found = null;
        foreach ($dtos as $dto) {
            if ($dto->month() === '2026-01') {
                $found = $dto;
                break;
            }
        }

        $this->assertNotNull($found);
        $this->assertEquals(100, $found->total());
        
        $domains = $found->domains();
        $this->assertCount(3, $domains);

        // Verify each DomainCount object has correct percentage based on total
        foreach ($domains as $domainCount) {
            $domain = $domainCount->domain();
            $count = $domainCount->count();
            $percentage = $domainCount->percentage();

            if ($domain === 'example.com') {
                $this->assertEquals(50, $count);
                $this->assertEquals('50.0', $percentage, 'Expected 50.0% for example.com (50/100)');
            } elseif ($domain === 'other.com') {
                $this->assertEquals(30, $count);
                $this->assertEquals('30.0', $percentage, 'Expected 30.0% for other.com (30/100)');
            } elseif ($domain === 'third.com') {
                $this->assertEquals(20, $count);
                $this->assertEquals('20.0', $percentage, 'Expected 20.0% for third.com (20/100)');
            }
        }
    }

    public function testTicketStatisticsDomainCountsHaveCorrectPercentages(): void
    {
        $repo = $this->createMock(EmailSentRepository::class);

        $raw = [
            ['month' => '2026-01', 'domains' => ['company-a.com' => 60, 'company-b.com' => 30, 'company-c.com' => 10], 'total_tickets' => 100],
        ];

        $repo->method('getMonthlyDomainCountsRows')
            ->willReturnCallback(function($distinct, $since) use ($raw) {
                $this->assertEquals('ticket_id', $distinct);
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

        $cache = $this->createMock(\Symfony\Contracts\Cache\CacheInterface::class);
        $cache->method('get')
            ->willReturnCallback(function($key, $callback) {
                $item = $this->createMock(\Symfony\Contracts\Cache\ItemInterface::class);
                return $callback($item);
            });

        $service = new StatisticsService($repo, $clock, $cache);
        $dtos = $service->getMonthlyTicketStatisticsByDomain();

        // Find DTO for 2026-01
        $found = null;
        foreach ($dtos as $dto) {
            if ($dto->month() === '2026-01') {
                $found = $dto;
                break;
            }
        }

        $this->assertNotNull($found);
        $this->assertEquals(100, $found->total());
        
        $domains = $found->domains();
        $this->assertCount(3, $domains);

        // Verify each DomainCount object calculates correct percentages
        foreach ($domains as $domainCount) {
            $domain = $domainCount->domain();
            $percentage = $domainCount->percentage();

            if ($domain === 'company-a.com') {
                $this->assertEquals('60.0', $percentage, 'Expected 60.0% for company-a.com');
            } elseif ($domain === 'company-b.com') {
                $this->assertEquals('30.0', $percentage, 'Expected 30.0% for company-b.com');
            } elseif ($domain === 'company-c.com') {
                $this->assertEquals('10.0', $percentage, 'Expected 10.0% for company-c.com');
            }
        }
    }

    public function testDomainCountsWithComplexPercentages(): void
    {
        $repo = $this->createMock(EmailSentRepository::class);

        // Test with numbers that result in decimal percentages
        $raw = [
            ['month' => '2026-01', 'domains' => ['example.com' => 1, 'other.com' => 2], 'total_users' => 3],
        ];

        $repo->method('getMonthlyDomainCountsRows')
            ->willReturnCallback(function($distinct, $since) use ($raw) {
                $rows = [];
                foreach ($raw as $stat) {
                    foreach ($stat['domains'] as $domain => $count) {
                        $rows[] = ['month' => $stat['month'], 'domain' => $domain, 'count' => $count];
                    }
                }
                return $rows;
            });

        $repo->method('getNewUsersByMonth')
            ->willReturn(['2026-01' => 1]);

        $clock = $this->createMock(\App\Service\ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-15'));

        $cache = $this->createMock(\Symfony\Contracts\Cache\CacheInterface::class);
        $cache->method('get')
            ->willReturnCallback(function($key, $callback) {
                $item = $this->createMock(\Symfony\Contracts\Cache\ItemInterface::class);
                return $callback($item);
            });

        $service = new StatisticsService($repo, $clock, $cache);
        $dtos = $service->getMonthlyUserStatisticsByDomain();

        // Find DTO for 2026-01
        $found = null;
        foreach ($dtos as $dto) {
            if ($dto->month() === '2026-01') {
                $found = $dto;
                break;
            }
        }

        $this->assertNotNull($found);
        $domains = $found->domains();

        // Verify percentages are calculated with proper decimal precision
        foreach ($domains as $domainCount) {
            if ($domainCount->domain() === 'example.com') {
                // 1/3 = 33.333... -> should be formatted as 33.3%
                $this->assertEquals('33.3', $domainCount->percentage());
            } elseif ($domainCount->domain() === 'other.com') {
                // 2/3 = 66.666... -> should be formatted as 66.7%
                $this->assertEquals('66.7', $domainCount->percentage());
            }
        }
    }

    public function testClearCacheDeletesAllCacheKeys(): void
    {
        $repo = $this->createMock(EmailSentRepository::class);
        $clock = $this->createMock(\App\Service\ClockInterface::class);
        $cache = $this->createMock(\Symfony\Contracts\Cache\CacheInterface::class);

        // Expect delete to be called for all possible month variations (1-12)
        $expectedDeleteCalls = 24; // 12 for user stats + 12 for ticket stats
        $cache->expects($this->exactly($expectedDeleteCalls))
            ->method('delete')
            ->willReturn(true);

        $service = new StatisticsService($repo, $clock, $cache);
        $service->clearCache();
    }

    public function testClearCurrentMonthCacheDeletesOnlyDefaultCache(): void
    {
        $repo = $this->createMock(EmailSentRepository::class);
        $clock = $this->createMock(\App\Service\ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-15'));
        
        $cache = $this->createMock(\Symfony\Contracts\Cache\CacheInterface::class);

        // Expect delete to be called only for the default 6-month cache (2 calls: user + ticket)
        $expectedDeleteCalls = 2;
        $cache->expects($this->exactly($expectedDeleteCalls))
            ->method('delete')
            ->withConsecutive(
                ['statistics.monthly_user_by_domain_6'],
                ['statistics.monthly_ticket_by_domain_6']
            )
            ->willReturn(true);

        $service = new StatisticsService($repo, $clock, $cache);
        $service->clearCurrentMonthCache();
    }
}
