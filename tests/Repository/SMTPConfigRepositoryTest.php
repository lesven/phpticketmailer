<?php

namespace App\Tests\Repository;

use App\Entity\SMTPConfig;
use App\Repository\SMTPConfigRepository;
use PHPUnit\Framework\TestCase;

class SMTPConfigRepositoryTest extends TestCase
{
    public function testGetConfigReturnsNullWhenNoConfigExists(): void
    {
        $mockRepository = $this->createMock(SMTPConfigRepository::class);
        $mockRepository
            ->method('getConfig')
            ->willReturn(null);

        $result = $mockRepository->getConfig();

        $this->assertNull($result);
    }

    public function testGetConfigReturnsFirstConfig(): void
    {
        $smtpConfig = $this->createMock(SMTPConfig::class);
        $mockRepository = $this->createMock(SMTPConfigRepository::class);
        $mockRepository
            ->method('getConfig')
            ->willReturn($smtpConfig);

        $result = $mockRepository->getConfig();

        $this->assertSame($smtpConfig, $result);
    }
}
