<?php

namespace App\Tests\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use PHPUnit\Framework\TestCase;

class UserRepositoryTest extends TestCase
{
    public function testFindByUsernameReturnsNullWhenUserNotFound(): void
    {
        $mockRepository = $this->createMock(UserRepository::class);
        $mockRepository
            ->method('findByUsername')
            ->willReturn(null);

        $result = $mockRepository->findByUsername('nonexistent');

        $this->assertNull($result);
    }

    public function testFindByUsernameReturnsUser(): void
    {
        $user = $this->createMock(User::class);
        $mockRepository = $this->createMock(UserRepository::class);
        $mockRepository
            ->method('findByUsername')
            ->willReturn($user);

        $result = $mockRepository->findByUsername('existinguser');

        $this->assertSame($user, $result);
    }
}
