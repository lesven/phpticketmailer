<?php

namespace App\Tests\Repository;

use App\Repository\UserRepository;
use App\Entity\User;
use App\ValueObject\Username;
use App\ValueObject\EmailAddress;
use PHPUnit\Framework\TestCase;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\AbstractQuery;

class UserRepositoryIdentifyUnknownUsersTest extends TestCase
{
    private UserRepository $repository;
    private ManagerRegistry $registry;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->registry->method('getManagerForClass')->willReturn($this->entityManager);
        $this->repository = new UserRepository($this->registry);
    }

    /**
     * Test für das ursprüngliche Problem: identifyUnknownUsers crasht bei ungültigen Benutzernamen
     */
    public function testIdentifyUnknownUsersWithInvalidUsernamesDoesNotCrash(): void
    {
        $usernames = [
            'valid.user' => true,
            '.invalid' => true,        // Punkt am Anfang - sollte ignoriert werden
            'invalid.' => true,        // Punkt am Ende - sollte ignoriert werden  
            'user space' => true,      // Leerzeichen - sollte ignoriert werden
            'normal_user' => true,     // gültig
        ];

        // Mock findMultipleByUsernames to return empty array (keine Benutzer gefunden)
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(AbstractQuery::class);
        
        $this->entityManager->method('createQueryBuilder')->willReturn($queryBuilder);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $query->method('getResult')->willReturn([]);

        // Dies sollte nicht crashen, sondern nur die gültigen Benutzernamen zurückgeben
        $result = $this->repository->identifyUnknownUsers($usernames);

        // Nur die gültigen Benutzernamen sollten als unbekannt identifiziert werden
        $this->assertContains('valid.user', $result);
        $this->assertContains('normal_user', $result);
        $this->assertNotContains('.invalid', $result);
        $this->assertNotContains('invalid.', $result);
        $this->assertNotContains('user space', $result);
        
        // Stelle sicher, dass genau 2 gültige Benutzernamen zurückgegeben werden
        $this->assertCount(2, $result);
    }

    /**
     * Test für den Fall, dass alle Benutzernamen ungültig sind
     */
    public function testIdentifyUnknownUsersWithOnlyInvalidUsernames(): void
    {
        $usernames = [
            '.invalid1' => true,
            'invalid2.' => true,
            'user space' => true,
            'user<script>' => true,
        ];

        // Mock findMultipleByUsernames
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(AbstractQuery::class);
        
        $this->entityManager->method('createQueryBuilder')->willReturn($queryBuilder);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $query->method('getResult')->willReturn([]);

        // Sollte ein leeres Array zurückgeben, nicht crashen
        $result = $this->repository->identifyUnknownUsers($usernames);
        
        $this->assertEmpty($result);
    }

    /**
     * Test für normale gültige Benutzernamen mit Punkten
     */
    public function testIdentifyUnknownUsersWithValidDottedUsernames(): void
    {
        $usernames = [
            'h.asakura' => true,
            'max.mustermann' => true,
            'user.name.test' => true,
        ];

        // Mock findMultipleByUsernames
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(AbstractQuery::class);
        
        $this->entityManager->method('createQueryBuilder')->willReturn($queryBuilder);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $query->method('getResult')->willReturn([]);

        // Alle sollten als unbekannt identifiziert werden
        $result = $this->repository->identifyUnknownUsers($usernames);
        
        $this->assertCount(3, $result);
        $this->assertContains('h.asakura', $result);
        $this->assertContains('max.mustermann', $result);
        $this->assertContains('user.name.test', $result);
    }
}
