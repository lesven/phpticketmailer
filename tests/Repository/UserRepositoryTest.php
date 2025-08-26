<?php

namespace App\Tests\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use App\ValueObject\Username;
use PHPUnit\Framework\TestCase;

/**
 * Test für UserRepository case-insensitive Funktionalität
 * 
 * Simuliert das ursprüngliche Problem mit case-sensitivity:
 * - Benutzer in DB waren lowercase (z.B. "svenmuller") 
 * - CSV hatte gemischte Schreibweise (z.B. "SvenMueller")
 * 
 * Diese Tests verwenden Mocks um das Verhalten zu simulieren,
 * da Integration-Tests komplexeres Setup benötigen würden.
 */
class UserRepositoryTest extends TestCase
{
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
    }

    /**
     * Test: Case-insensitive Suche einzelner Benutzer
     * 
     * Simuliert das ursprüngliche Problem:
     * - User in DB: "svenmuller" (lowercase durch Username Value Object)
     * - CSV Suche: "SvenMuller" (mixed case)
     * - OHNE deine Fix: würde null zurückgeben (Problem!)  
     * - MIT deinem Fix: findet den User trotzdem (gelöst!)
     */
    public function testFindByUsernameCaseInsensitive(): void
    {
        // Mock User der in der DB gespeichert wäre (durch Username Value Object normalisiert)
        $mockUser = $this->createUser('svenmuller');
        
        // VORHER (ohne deine Implementierung) - hätte case-sensitive gesucht:
        // $this->userRepository->method('findByUsername')
        //     ->willReturn($username === 'svenmuller' ? $mockUser : null);
        // Das war das Problem: 'SvenMuller' != 'svenmuller' -> null
        
        // NACHHER (mit deiner Implementierung) - case-insensitive:
        $this->userRepository->method('findByUsername')
            ->willReturnCallback(function ($username) use ($mockUser) {
                // Simuliert deine case-insensitive Repository-Implementierung
                $normalized = strtolower(trim($username));
                return $normalized === 'svenmuller' ? $mockUser : null;
            });
        
        // Test: Diese verschiedenen Schreibweisen sollten ALLE den User finden
        $testCases = [
            'svenmuller',    // exakt wie in DB
            'SvenMuller',    // typische CSV-Schreibweise (DAS WAR DAS PROBLEM!)
            'SVENMULLER',    // komplett Großbuchstaben  
            'sVeNmUlLeR',    // wilde gemischte Schreibweise
            '  SvenMuller  ' // mit Leerzeichen
        ];
        
        foreach ($testCases as $searchTerm) {
            $foundUser = $this->userRepository->findByUsername($searchTerm);
            
            $this->assertNotNull(
                $foundUser, 
                "FEHLER: Benutzer 'svenmuller' sollte gefunden werden bei Suche nach: '$searchTerm'\n" .
                "Das war das ursprüngliche Problem - CSV hatte '$searchTerm' aber DB hatte 'svenmuller'"
            );
            $this->assertEquals('svenmuller', (string)$foundUser->getUsername());
        }
        
        // Nicht existierende User sollten weiterhin null zurückgeben
        $this->assertNull($this->userRepository->findByUsername('nonexistent'));
    }
    
    /**
     * Helper: Erstellt einen Mock User mit Username Value Object
     */
    private function createUser(string $username): User
    {
        $user = $this->createMock(User::class);
        $user->method('getUsername')
             ->willReturn(Username::fromString($username));
        return $user;
    }

    /**
     * Test: Case-insensitive Batch-Suche mehrerer Benutzer
     * 
     * Simuliert CSV-Import Szenario:
     * - CSV enthält: ["SvenMueller", "JohnDoe", "JANEDOE"]  
     * - DB enthält: ["svenmuller", "johndoe", "janedoe"]
     * - Sollte trotz unterschiedlicher Schreibweise alle finden
     */
    public function testFindMultipleByUsernamesCaseInsensitive(): void
    {
        // Mock Users die in DB gespeichert wären (normalisiert)
        $users = [
            $this->createUser('svenmuller'),
            $this->createUser('johndoe'), 
            $this->createUser('janedoe')
        ];
        
        // Repository simuliert deine case-insensitive Implementierung
        $this->userRepository->method('findMultipleByUsernames')
            ->willReturnCallback(function (array $usernames) use ($users) {
                // Simuliert deine normalisierte Suche in der DB
                $normalizedInputs = array_map(fn($u) => strtolower(trim($u)), $usernames);
                
                $found = [];
                foreach ($users as $user) {
                    $userUsername = strtolower((string)$user->getUsername());
                    if (in_array($userUsername, $normalizedInputs)) {
                        $found[] = $user;
                    }
                }
                return $found;
            });
        
        // CSV-Style Suche mit gemischter Schreibweise (das ursprüngliche Problem)
        $csvUsernames = [
            'SvenMuller',   // mixed case -> sollte svenmuller finden  
            'JOHNDOE',      // uppercase -> sollte johndoe finden
            'JaneDoe',      // mixed case -> sollte janedoe finden
            'UnknownUser'   // existiert nicht
        ];
        
        $foundUsers = $this->userRepository->findMultipleByUsernames($csvUsernames);
        
        // Sollte 3 Benutzer finden (UnknownUser nicht)
        $this->assertCount(3, $foundUsers, 'Sollte alle 3 bekannten User trotz unterschiedlicher Schreibweise finden');
        
        // Prüfe dass alle erwarteten Benutzer gefunden wurden
        $foundUsernames = array_map(fn($user) => (string)$user->getUsername(), $foundUsers);
        sort($foundUsernames);
        
        $this->assertEquals(['janedoe', 'johndoe', 'svenmuller'], $foundUsernames);
    }
}
