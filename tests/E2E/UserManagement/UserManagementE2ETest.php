<?php

namespace App\Tests\E2E\UserManagement;

use App\Entity\User;
use App\Service\UserCreator;
use App\Tests\E2E\AbstractE2ETestCase;

/**
 * E2E Test: Benutzer-Verwaltungs-Workflow
 *
 * Testet CRUD-Operationen auf User-Entitäten mit echter Datenbank.
 * Wird übersprungen wenn die Datenbank nicht verfügbar ist.
 */
class UserManagementE2ETest extends AbstractE2ETestCase
{
    private UserCreator $userCreator;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->userCreator = static::getContainer()->get(UserCreator::class);
        } catch (\Exception $e) {
            $this->markTestSkipped('UserCreator service not available: ' . $e->getMessage());
        }
    }

    public function testCreateUserPersistsToDatabase(): void
    {
        $user = $this->userCreator->createUser('test_e2e_user', 'test_e2e@example.com');
        $this->userCreator->flush();

        $savedUser = $this->userRepository->findOneBy(['username' => 'test_e2e_user']);

        $this->assertNotNull($savedUser);
        $this->assertSame('test_e2e_user', (string) $savedUser->getUsername());
        $this->assertSame('test_e2e@example.com', (string) $savedUser->getEmail());
    }

    public function testNewUserIsDefaultlyIncludedInSurveys(): void
    {
        $user = $this->userCreator->createUser('survey_user', 'survey@example.com');
        $this->userCreator->flush();

        $this->assertFalse($user->isExcludedFromSurveys());
        $this->assertTrue($user->isEligibleForEmailNotifications());
    }

    public function testExcludeUserFromSurveys(): void
    {
        $user = $this->userCreator->createUser('exclude_user', 'exclude@example.com');
        $user->excludeFromSurveys();
        $this->userCreator->flush();

        $savedUser = $this->userRepository->findOneBy(['username' => 'exclude_user']);

        $this->assertNotNull($savedUser);
        $this->assertTrue($savedUser->isExcludedFromSurveys());
        $this->assertFalse($savedUser->isEligibleForEmailNotifications());
    }

    public function testIncludeUserBackInSurveys(): void
    {
        // Create and exclude user
        $user = $this->userCreator->createUser('toggle_user', 'toggle@example.com');
        $user->excludeFromSurveys();
        $this->userCreator->flush();

        // Now include again
        $savedUser = $this->userRepository->findOneBy(['username' => 'toggle_user']);
        $this->assertNotNull($savedUser);
        $savedUser->includeInSurveys();
        $this->entityManager->flush();

        $this->assertFalse($savedUser->isExcludedFromSurveys());
        $this->assertTrue($savedUser->isEligibleForEmailNotifications());
    }

    public function testFindUserByUsername(): void
    {
        $this->userCreator->createUser('findme_user', 'findme@example.com');
        $this->userCreator->flush();

        $user = $this->userRepository->findByUsername('findme_user');

        $this->assertNotNull($user);
        $this->assertSame('findme_user', (string) $user->getUsername());
    }

    public function testFindNonexistentUserReturnsNull(): void
    {
        $user = $this->userRepository->findByUsername('nonexistent_xyz_user');

        $this->assertNull($user);
    }

    public function testCreateMultipleUsersAndCountThem(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->userCreator->createUser("batch_user_{$i}", "batch{$i}@example.com");
        }
        $this->userCreator->flush();

        $allUsers = $this->userRepository->findAll();

        $this->assertCount(5, $allUsers);
    }

    public function testUpdateUserEmailPersists(): void
    {
        $user = $this->userCreator->createUser('email_update_user', 'old@example.com');
        $this->userCreator->flush();

        $user->setEmail('new@example.com');
        $this->entityManager->flush();

        $this->entityManager->clear();
        $reloaded = $this->userRepository->findByUsername('email_update_user');

        $this->assertSame('new@example.com', (string) $reloaded->getEmail());
    }

    public function testUserWithInvalidEmailThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->userCreator->createUser('bad_email_user', 'not-an-email');
    }
}
