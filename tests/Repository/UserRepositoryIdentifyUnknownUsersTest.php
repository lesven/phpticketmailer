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
        
        // Mock ClassMetadata für Doctrine
        $classMetadata = $this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class);
        $classMetadata->name = User::class;
        
        $this->registry->method('getManagerForClass')->willReturn($this->entityManager);
        $this->entityManager->method('getClassMetadata')->willReturn($classMetadata);
        
        // Create a partial mock that allows us to test identifyUnknownUsers while mocking findMultipleByUsernames
        $this->repository = $this->getMockBuilder(UserRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['findMultipleByUsernames'])
            ->getMock();
    }

    /**
     * Test für den Fall, dass ungültige Benutzernamen dabei sind
     * Diese sollten stillschweigend ignoriert werden
     */
    public function testIdentifyUnknownUsersWithInvalidUsernamesDoesNotCrash(): void
    {
        $usernames = [
            'valid.user' => true,      // Gültiger Username
            'normal_user' => true,     // Gültiger Username
            '.invalid.start' => true,  // Ungültiger Username (startet mit Punkt)
            'invalid.end.' => true,    // Ungültiger Username (endet mit Punkt)
            'user space' => true,      // Ungültiger Username (Leerzeichen)
        ];

        // Mock findMultipleByUsernames to return empty array (keine Benutzer gefunden)
        $this->repository->method('findMultipleByUsernames')->willReturn([]);

        // Dies sollte nicht crashen, sondern nur die gültigen Benutzernamen zurückgeben
        // Ungültige Benutzernamen werden stillschweigend ignoriert
        $result = $this->repository->identifyUnknownUsers($usernames);

        // Nur die gültigen Benutzernamen sollten als unbekannt identifiziert werden
        $this->assertContains('valid.user', $result);
        $this->assertContains('normal_user', $result);
        $this->assertNotContains('.invalid.start', $result);
        $this->assertNotContains('invalid.end.', $result);
        $this->assertNotContains('user space', $result);
        
        // Stelle sicher, dass genau 2 gültige Benutzernamen zurückgegeben werden
        $this->assertCount(2, $result);
    }

    /**
     * Test für den Fall, dass alle Benutzernamen ungültig sind
     * Sollte ein leeres Array zurückgeben, nicht crashen
     */
    public function testIdentifyUnknownUsersWithOnlyInvalidUsernames(): void
    {
        $usernames = [
            '.invalid1' => true,       // Startet mit Punkt
            'invalid2.' => true,       // Endet mit Punkt
            'user space' => true,      // Enthält Leerzeichen
            'user<script>' => true,    // Enthält ungültige Zeichen
        ];

        // Mock findMultipleByUsernames
        $this->repository->method('findMultipleByUsernames')->willReturn([]);

        // Sollte ein leeres Array zurückgeben, da alle Benutzernamen ungültig sind
        // und stillschweigend ignoriert werden
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
        $this->repository->method('findMultipleByUsernames')->willReturn([]);

        // Alle sollten als unbekannt identifiziert werden
        $result = $this->repository->identifyUnknownUsers($usernames);
        
        $this->assertCount(3, $result);
        $this->assertContains('h.asakura', $result);
        $this->assertContains('max.mustermann', $result);
        $this->assertContains('user.name.test', $result);
    }
}
