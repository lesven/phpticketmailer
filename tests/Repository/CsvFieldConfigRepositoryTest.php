<?php

namespace App\Tests\Repository;

use App\Entity\CsvFieldConfig;
use App\Repository\CsvFieldConfigRepository;
use PHPUnit\Framework\TestCase;

class CsvFieldConfigRepositoryTest extends TestCase
{
    public function testGetCurrentConfigCreatesNewConfigWhenNoneExists(): void
    {
        $mockRepository = $this->createMock(CsvFieldConfigRepository::class);
        $mockRepository
            ->method('getCurrentConfig')
            ->willReturn(new CsvFieldConfig());

        $result = $mockRepository->getCurrentConfig();

        $this->assertInstanceOf(CsvFieldConfig::class, $result);
    }

    public function testSaveConfigPersistsAndFlushes(): void
    {
        $config = $this->createMock(CsvFieldConfig::class);
        $mockRepository = $this->createMock(CsvFieldConfigRepository::class);
        $mockRepository
            ->expects($this->once())
            ->method('saveConfig')
            ->with($config);

        $mockRepository->saveConfig($config);
    }
}
