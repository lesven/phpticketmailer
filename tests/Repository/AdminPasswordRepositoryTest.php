<?php

namespace App\Tests\Repository;

use App\Entity\AdminPassword;
use App\Repository\AdminPasswordRepository;
use PHPUnit\Framework\TestCase;

class AdminPasswordRepositoryTest extends TestCase
{
    public function testFindFirstReturnsNullWhenNoEntriesExist(): void
    {
        $mockRepository = $this->createMock(AdminPasswordRepository::class);
        $mockRepository
            ->method('findFirst')
            ->willReturn(null);

        $result = $mockRepository->findFirst();

        $this->assertNull($result);
    }

    public function testFindFirstReturnsFirstEntry(): void
    {
        $adminPassword = $this->createMock(AdminPassword::class);

        $mockRepository = $this->createMock(AdminPasswordRepository::class);
        $mockRepository
            ->method('findFirst')
            ->willReturn($adminPassword);

        $result = $mockRepository->findFirst();

        $this->assertSame($adminPassword, $result);
    }
}
