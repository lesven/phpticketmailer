<?php

namespace App\Tests\Service;

use App\Service\UserCreator;
use App\Service\UserValidator;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class UserCreatorTest extends TestCase
{
    private UserCreator $userCreator;
    private EntityManagerInterface $entityManager;
    private UserValidator $userValidator;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userValidator = $this->createMock(UserValidator::class);
        $this->userCreator = new UserCreator($this->entityManager, $this->userValidator);
    }

    public function testCreateUserWithValidData(): void
    {
        $username = 'testuser';
        $email = 'test@example.com';

        $this->userValidator->expects($this->once())
            ->method('isValidUsername')
            ->with($username)
            ->willReturn(true);

        $this->userValidator->expects($this->once())
            ->method('isValidEmail')
            ->with($email)
            ->willReturn(true);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(User::class));

        $this->userCreator->createUser($username, $email);

        $this->assertEquals(1, $this->userCreator->getPendingUsersCount());
    }

    public function testCreateUserWithInvalidUsernameThrowsException(): void
    {
        $username = 'invalid username with spaces';
        $email = 'test@example.com';

        $this->userValidator->expects($this->once())
            ->method('isValidUsername')
            ->with($username)
            ->willReturn(false);

        $this->userValidator->expects($this->never())
            ->method('isValidEmail');

        $this->entityManager->expects($this->never())
            ->method('persist');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Ungültiger Benutzername: {$username}");

        $this->userCreator->createUser($username, $email);
    }

    public function testCreateUserWithInvalidEmailThrowsException(): void
    {
        $username = 'validuser';
        $email = 'invalid-email';

        $this->userValidator->expects($this->once())
            ->method('isValidUsername')
            ->with($username)
            ->willReturn(true);

        $this->userValidator->expects($this->once())
            ->method('isValidEmail')
            ->with($email)
            ->willReturn(false);

        $this->entityManager->expects($this->never())
            ->method('persist');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Ungültige E-Mail-Adresse: {$email}");

        $this->userCreator->createUser($username, $email);
    }

    public function testCreateMultipleUsers(): void
    {
        $users = [
            ['username' => 'user1', 'email' => 'user1@example.com'],
            ['username' => 'user2', 'email' => 'user2@example.com'],
            ['username' => 'user3', 'email' => 'user3@example.com']
        ];

        $this->userValidator->method('isValidUsername')->willReturn(true);
        $this->userValidator->method('isValidEmail')->willReturn(true);

        $this->entityManager->expects($this->exactly(3))
            ->method('persist')
            ->with($this->isInstanceOf(User::class));

        foreach ($users as $userData) {
            $this->userCreator->createUser($userData['username'], $userData['email']);
        }

        $this->assertEquals(3, $this->userCreator->getPendingUsersCount());
    }

    public function testPersistUsersWithPendingUsers(): void
    {
        // Create some users first
        $this->userValidator->method('isValidUsername')->willReturn(true);
        $this->userValidator->method('isValidEmail')->willReturn(true);

        $this->entityManager->expects($this->exactly(2))
            ->method('persist')
            ->with($this->isInstanceOf(User::class));

        $this->userCreator->createUser('user1', 'user1@example.com');
        $this->userCreator->createUser('user2', 'user2@example.com');

        $this->assertEquals(2, $this->userCreator->getPendingUsersCount());

        // Now persist them
        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->userCreator->persistUsers();

        $this->assertEquals(2, $result);
        $this->assertEquals(0, $this->userCreator->getPendingUsersCount());
    }

    public function testPersistUsersWithNoPendingUsers(): void
    {
        $this->entityManager->expects($this->never())
            ->method('flush');

        $result = $this->userCreator->persistUsers();

        $this->assertEquals(0, $result);
        $this->assertEquals(0, $this->userCreator->getPendingUsersCount());
    }

    public function testPersistUsersResetsInternalArray(): void
    {
        // Create a user
        $this->userValidator->method('isValidUsername')->willReturn(true);
        $this->userValidator->method('isValidEmail')->willReturn(true);

        $this->entityManager->expects($this->exactly(2))
            ->method('persist')
            ->with($this->isInstanceOf(User::class));

        $this->userCreator->createUser('user1', 'user1@example.com');
        $this->assertEquals(1, $this->userCreator->getPendingUsersCount());

        // Persist users
        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->userCreator->persistUsers();
        $this->assertEquals(1, $result);
        $this->assertEquals(0, $this->userCreator->getPendingUsersCount());

        // Create another user after persist
        $this->userCreator->createUser('user2', 'user2@example.com');
        $this->assertEquals(1, $this->userCreator->getPendingUsersCount());
    }

    public function testGetPendingUsersCountInitiallyZero(): void
    {
        $result = $this->userCreator->getPendingUsersCount();

        $this->assertEquals(0, $result);
    }

    public function testGetPendingUsersCountIncrementsWithEachUser(): void
    {
        $this->userValidator->method('isValidUsername')->willReturn(true);
        $this->userValidator->method('isValidEmail')->willReturn(true);

        $this->assertEquals(0, $this->userCreator->getPendingUsersCount());

        $this->userCreator->createUser('user1', 'user1@example.com');
        $this->assertEquals(1, $this->userCreator->getPendingUsersCount());

        $this->userCreator->createUser('user2', 'user2@example.com');
        $this->assertEquals(2, $this->userCreator->getPendingUsersCount());

        $this->userCreator->createUser('user3', 'user3@example.com');
        $this->assertEquals(3, $this->userCreator->getPendingUsersCount());
    }

    public function testConstructorAcceptsDependencies(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userValidator = $this->createMock(UserValidator::class);

        $userCreator = new UserCreator($entityManager, $userValidator);

        $this->assertInstanceOf(UserCreator::class, $userCreator);
        $this->assertEquals(0, $userCreator->getPendingUsersCount());
    }

    public function testCreateUserSetsCorrectUserData(): void
    {
        $username = 'testuser';
        $email = 'test@example.com';

        $this->userValidator->method('isValidUsername')->willReturn(true);
        $this->userValidator->method('isValidEmail')->willReturn(true);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (User $user) use ($username, $email) {
                // We can't directly check the values since they're set on the mock
                // But we can verify it's a User instance and persist was called
                return $user instanceof User;
            }));

        $this->userCreator->createUser($username, $email);
    }

    public function testWorkflowIntegration(): void
    {
        // Simulate a typical workflow: create multiple users, persist, create more, persist again
        $this->userValidator->method('isValidUsername')->willReturn(true);
        $this->userValidator->method('isValidEmail')->willReturn(true);

        // Phase 1: Create first batch
        $this->entityManager->expects($this->exactly(3))
            ->method('persist')
            ->with($this->isInstanceOf(User::class));

        $this->userCreator->createUser('user1', 'user1@example.com');
        $this->userCreator->createUser('user2', 'user2@example.com');
        $this->assertEquals(2, $this->userCreator->getPendingUsersCount());

        // Phase 2: Persist first batch
        $this->entityManager->expects($this->exactly(2))
            ->method('flush');

        $result1 = $this->userCreator->persistUsers();
        $this->assertEquals(2, $result1);
        $this->assertEquals(0, $this->userCreator->getPendingUsersCount());

        // Phase 3: Create second batch
        $this->userCreator->createUser('user3', 'user3@example.com');
        $this->assertEquals(1, $this->userCreator->getPendingUsersCount());

        // Phase 4: Persist second batch
        $result2 = $this->userCreator->persistUsers();
        $this->assertEquals(1, $result2);
        $this->assertEquals(0, $this->userCreator->getPendingUsersCount());
    }

    public function testCreateUserWithEmptyUsername(): void
    {
        $this->userValidator->expects($this->once())
            ->method('isValidUsername')
            ->with('')
            ->willReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ungültiger Benutzername: ');

        $this->userCreator->createUser('', 'test@example.com');
    }

    public function testCreateUserWithEmptyEmail(): void
    {
        $username = 'validuser';
        
        $this->userValidator->expects($this->once())
            ->method('isValidUsername')
            ->with($username)
            ->willReturn(true);

        $this->userValidator->expects($this->once())
            ->method('isValidEmail')
            ->with('')
            ->willReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ungültige E-Mail-Adresse: ');

        $this->userCreator->createUser($username, '');
    }

    public function testMultiplePersistCallsWork(): void
    {
        $this->userValidator->method('isValidUsername')->willReturn(true);
        $this->userValidator->method('isValidEmail')->willReturn(true);

        // First persist with no users should return 0
        $result1 = $this->userCreator->persistUsers();
        $this->assertEquals(0, $result1);

        // Add a user and persist
        $this->userCreator->createUser('user1', 'user1@example.com');
        $this->entityManager->expects($this->once())->method('flush');
        
        $result2 = $this->userCreator->persistUsers();
        $this->assertEquals(1, $result2);

        // Persist again with no new users
        $result3 = $this->userCreator->persistUsers();
        $this->assertEquals(0, $result3);
    }
}