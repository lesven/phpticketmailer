<?php

namespace App\Tests\Service;

use App\Service\UserCreator;
use App\Entity\User;
use App\Exception\InvalidUsernameException;
use App\Exception\InvalidEmailAddressException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Vereinfachte Test-Klasse für den UserCreator
 * 
 * Diese Tests fokussieren sich auf das Verhalten mit Value Objects
 * ohne komplexe Mock-Expectations.
 */
class UserCreatorTest extends TestCase
{
    private UserCreator $userCreator;
    private $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userCreator = new UserCreator($this->entityManager);
    }

    /**
     * Testet das Erstellen eines Benutzers mit gültigen Daten
     */
    public function testCreateUserWithValidData(): void
    {
        $username = 'testuser';
        $email = 'test@example.com';

        // Sollte keine Exception werfen
        $this->userCreator->createUser($username, $email);

        $this->assertEquals(1, $this->userCreator->getPendingUsersCount());
    }

    /**
     * Testet das Erstellen eines Benutzers mit ungültigem Username
     */
    public function testCreateUserWithInvalidUsernameThrowsException(): void
    {
        $invalidUsername = 'a'; // Zu kurz für Username Value Object
        $email = 'test@example.com';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ungültiger Benutzername');

        $this->userCreator->createUser($invalidUsername, $email);
    }

    /**
     * Testet das Erstellen eines Benutzers mit ungültiger E-Mail
     */
    public function testCreateUserWithInvalidEmailThrowsException(): void
    {
        $username = 'testuser';
        $invalidEmail = 'invalid-email'; // Ungültige E-Mail für EmailAddress Value Object

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ungültige E-Mail-Adresse');

        $this->userCreator->createUser($username, $invalidEmail);
    }

    /**
     * Testet das Erstellen mehrerer Benutzer
     */
    public function testCreateMultipleUsers(): void
    {
        $this->userCreator->createUser('user1', 'user1@example.com');
        $this->userCreator->createUser('user2', 'user2@example.com');
        $this->userCreator->createUser('user3', 'user3@example.com');

        $this->assertEquals(3, $this->userCreator->getPendingUsersCount());
    }

    /**
     * Testet das Persistieren ohne pending Users
     */
    public function testPersistUsersWithNoPendingUsers(): void
    {
        $result = $this->userCreator->persistUsers();

        $this->assertEquals(0, $result);
    }

    /**
     * Testet dass der Pending-Users-Zähler korrekt funktioniert
     */
    public function testGetPendingUsersCountIncrementsWithEachUser(): void
    {
        $this->assertEquals(0, $this->userCreator->getPendingUsersCount());

        $this->userCreator->createUser('user1', 'user1@example.com');
        $this->assertEquals(1, $this->userCreator->getPendingUsersCount());

        $this->userCreator->createUser('user2', 'user2@example.com');
        $this->assertEquals(2, $this->userCreator->getPendingUsersCount());
    }

    /**
     * Testet die korrekte Initialisierung des UserCreator-Konstruktors
     */
    public function testConstructorAcceptsDependencies(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userCreator = new UserCreator($entityManager);

        $this->assertInstanceOf(UserCreator::class, $userCreator);
        $this->assertEquals(0, $userCreator->getPendingUsersCount());
    }

    /**
     * Testet das Erstellen eines Benutzers mit leerem Username
     */
    public function testCreateUserWithEmptyUsername(): void
    {
        $emptyUsername = '';
        $email = 'test@example.com';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ungültiger Benutzername');

        $this->userCreator->createUser($emptyUsername, $email);
    }

    /**
     * Testet das Erstellen eines Benutzers mit leerer E-Mail
     */
    public function testCreateUserWithEmptyEmail(): void
    {
        $username = 'testuser';
        $emptyEmail = '';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ungültige E-Mail-Adresse');

        $this->userCreator->createUser($username, $emptyEmail);
    }

    /**
     * Testet Username Value Object Validierung - reservierte Namen
     */
    public function testCreateUserWithReservedUsername(): void
    {
        $reservedUsername = 'admin'; // Reservierter Name im Username Value Object
        $email = 'test@example.com';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ungültiger Benutzername');

        $this->userCreator->createUser($reservedUsername, $email);
    }

    /**
     * Testet Username Value Object Validierung - ungültige Zeichen
     */
    public function testCreateUserWithInvalidCharacters(): void
    {
        $invalidUsername = 'user@#$'; // Ungültige Zeichen für Username Value Object
        $email = 'test@example.com';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ungültiger Benutzername');

        $this->userCreator->createUser($invalidUsername, $email);
    }

    /**
     * Testet EmailAddress Value Object Validierung - ungültiges Format
     */
    public function testCreateUserWithMalformedEmail(): void
    {
        $username = 'testuser';
        $malformedEmail = 'test@'; // Unvollständige E-Mail für EmailAddress Value Object

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ungültige E-Mail-Adresse');

        $this->userCreator->createUser($username, $malformedEmail);
    }

    /**
     * Testet dass mit E-Mail-Adressen als Username funktioniert
     */
    public function testCreateUserWithEmailAsUsername(): void
    {
        $emailUsername = 'user@company.com'; // E-Mail als Username sollte funktionieren
        $email = 'user@company.com';

        // Sollte keine Exception werfen
        $this->userCreator->createUser($emailUsername, $email);

        $this->assertEquals(1, $this->userCreator->getPendingUsersCount());
    }

    /**
     * Testet dass normale Usernames funktionieren
     */
    public function testCreateUserWithNormalUsernames(): void
    {
        $testCases = [
            ['john.doe', 'john@example.com'],
            ['user123', 'user123@example.com'],
            ['test_user', 'test@example.com'],
            ['user-name', 'user@example.com']
        ];

        foreach ($testCases as [$username, $email]) {
            $this->userCreator->createUser($username, $email);
        }

        $this->assertEquals(4, $this->userCreator->getPendingUsersCount());
    }

    /**
     * Testet Persistierung mit gültigen Daten
     */
    public function testPersistUsersWithValidData(): void
    {
        $this->userCreator->createUser('user1', 'user1@example.com');
        $this->userCreator->createUser('user2', 'user2@example.com');

        $this->assertEquals(2, $this->userCreator->getPendingUsersCount());

        $result = $this->userCreator->persistUsers();

        $this->assertEquals(2, $result);
        $this->assertEquals(0, $this->userCreator->getPendingUsersCount());
    }

    /**
     * 🏗️ DDD: Testet die neue User Factory Method
     */
    public function testUserFactoryMethodCreatesValidUser(): void
    {
        $username = 'testuser';
        $email = 'test@example.com';

        // Erstelle User über Factory Method
        $user = \App\Entity\User::create($username, $email);

        $this->assertInstanceOf(\App\Entity\User::class, $user);
        $this->assertEquals($username, $user->getUsername()->getValue());
        $this->assertEquals($email, $user->getEmail()->getValue());
        $this->assertFalse($user->isExcludedFromSurveys());
        $this->assertTrue($user->isEligibleForEmailNotifications());
    }
}
