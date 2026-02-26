<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\AdminPassword;
use App\Exception\WeakPasswordException;
use App\Repository\AdminPasswordRepository;
use App\Service\AuthenticationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class AuthenticationServiceTest extends TestCase
{
    private AuthenticationService $service;
    private EntityManagerInterface $entityManager;
    private AdminPasswordRepository $adminPasswordRepo;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->adminPasswordRepo = $this->createMock(AdminPasswordRepository::class);

        $this->entityManager->method('getRepository')
            ->with(AdminPassword::class)
            ->willReturn($this->adminPasswordRepo);

        $this->service = new AuthenticationService($this->entityManager);
    }

    public function testAuthenticateWithCorrectPassword(): void
    {
        $adminPassword = new AdminPassword();
        $adminPassword->setPasswordFromPlaintext('CorrectP@ss123!');

        $this->adminPasswordRepo->method('findOneBy')
            ->with([], ['id' => 'ASC'])
            ->willReturn($adminPassword);

        $this->assertTrue($this->service->authenticate('CorrectP@ss123!'));
    }

    public function testAuthenticateWithWrongPassword(): void
    {
        $adminPassword = new AdminPassword();
        $adminPassword->setPasswordFromPlaintext('CorrectP@ss123!');

        $this->adminPasswordRepo->method('findOneBy')
            ->with([], ['id' => 'ASC'])
            ->willReturn($adminPassword);

        $this->assertFalse($this->service->authenticate('WrongP@ssword1'));
    }

    public function testAuthenticateCreatesDefaultPasswordWhenNoneExists(): void
    {
        $this->adminPasswordRepo->method('findOneBy')
            ->with([], ['id' => 'ASC'])
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(AdminPassword::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        // Default password should work
        $this->assertTrue($this->service->authenticate('DefaultP@ssw0rd123!'));
    }

    public function testAuthenticateCreatesDefaultPasswordRejectsWrongPassword(): void
    {
        $this->adminPasswordRepo->method('findOneBy')
            ->with([], ['id' => 'ASC'])
            ->willReturn(null);

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $this->assertFalse($this->service->authenticate('WrongPassword'));
    }

    public function testChangePasswordWithCorrectCurrentPassword(): void
    {
        $adminPassword = new AdminPassword();
        $adminPassword->setPasswordFromPlaintext('OldP@ssword123!');

        $this->adminPasswordRepo->method('findOneBy')
            ->with([], ['id' => 'ASC'])
            ->willReturn($adminPassword);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->changePassword('OldP@ssword123!', 'NewP@ssword456!');

        $this->assertTrue($result);
        $this->assertTrue($adminPassword->verifyPassword('NewP@ssword456!'));
    }

    public function testChangePasswordWithWrongCurrentPassword(): void
    {
        $adminPassword = new AdminPassword();
        $adminPassword->setPasswordFromPlaintext('CorrectP@ss123!');

        $this->adminPasswordRepo->method('findOneBy')
            ->with([], ['id' => 'ASC'])
            ->willReturn($adminPassword);

        $this->entityManager->expects($this->never())
            ->method('flush');

        $result = $this->service->changePassword('WrongPassword', 'NewP@ssword456!');

        $this->assertFalse($result);
    }

    public function testChangePasswordWithNoAdminPasswordExists(): void
    {
        $this->adminPasswordRepo->method('findOneBy')
            ->with([], ['id' => 'ASC'])
            ->willReturn(null);

        $this->entityManager->expects($this->never())
            ->method('flush');

        $result = $this->service->changePassword('any', 'NewP@ssword456!');

        $this->assertFalse($result);
    }

    public function testChangePasswordWithWeakNewPasswordThrowsException(): void
    {
        $adminPassword = new AdminPassword();
        $adminPassword->setPasswordFromPlaintext('OldP@ssword123!');

        $this->adminPasswordRepo->method('findOneBy')
            ->with([], ['id' => 'ASC'])
            ->willReturn($adminPassword);

        $this->expectException(WeakPasswordException::class);

        $this->service->changePassword('OldP@ssword123!', 'short');
    }
}
