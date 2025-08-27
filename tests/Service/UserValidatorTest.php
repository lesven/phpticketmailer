<?php

namespace App\Tests\Service;

use App\Service\UserValidator;
use App\Repository\UserRepository;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserValidatorTest extends TestCase
{
    private UserValidator $userValidator;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->userValidator = new UserValidator($this->userRepository);
    }

    /**
     * Testet die identifyUnknownUsers-Methode mit einem leeren Array
     * - Überprüft, dass ein leeres Array zurückgegeben wird, wenn keine Benutzernamen übergeben werden
     */
    public function testIdentifyUnknownUsersWithEmptyArray(): void
    {
        $result = $this->userValidator->identifyUnknownUsers([]);

        $this->assertEquals([], $result);
    }

    /**
     * Testet die identifyUnknownUsers-Methode wenn alle Benutzer bekannt sind
     * - Überprüft, dass ein leeres Array zurückgegeben wird, wenn alle Benutzernamen in der DB existieren
     */
    public function testIdentifyUnknownUsersWithAllKnownUsers(): void
    {
        $usernames = ['john' => true, 'jane' => true, 'bob' => true];

        $johnUser = $this->createUser('john');
        $janeUser = $this->createUser('jane');
        $bobUser = $this->createUser('bob');

        $this->userRepository->expects($this->once())
            ->method('findMultipleByUsernames')
            ->with(['john', 'jane', 'bob'])
            ->willReturn([$johnUser, $janeUser, $bobUser]);

        $result = $this->userValidator->identifyUnknownUsers($usernames);

        $this->assertEquals([], $result);
    }

    /**
     * Testet die identifyUnknownUsers-Methode wenn alle Benutzer unbekannt sind
     * - Überprüft, dass alle Benutzernamen zurückgegeben werden, wenn keiner in der DB existiert
     */
    public function testIdentifyUnknownUsersWithAllUnknownUsers(): void
    {
        $usernames = ['unknown1' => true, 'unknown2' => true, 'unknown3' => true];

        $this->userRepository->expects($this->once())
            ->method('findMultipleByUsernames')
            ->with(['unknown1', 'unknown2', 'unknown3'])
            ->willReturn([]);

        $result = $this->userValidator->identifyUnknownUsers($usernames);

        $this->assertEquals(['unknown1', 'unknown2', 'unknown3'], $result);
    }

    /**
     * Testet die identifyUnknownUsers-Methode mit einer Mischung aus bekannten und unbekannten Benutzern
     * - Überprüft, dass nur die unbekannten Benutzernamen zurückgegeben werden
     */
    public function testIdentifyUnknownUsersWithMixedUsers(): void
    {
        $usernames = ['known1' => true, 'unknown1' => true, 'known2' => true, 'unknown2' => true];

        $known1User = $this->createUser('known1');
        $known2User = $this->createUser('known2');

        $this->userRepository->expects($this->once())
            ->method('findMultipleByUsernames')
            ->with(['known1', 'unknown1', 'known2', 'unknown2'])
            ->willReturn([$known1User, $known2User]);

        $result = $this->userValidator->identifyUnknownUsers($usernames);

        $this->assertEquals(['unknown1', 'unknown2'], $result);
    }

    /**
     * Testet die isKnownUser-Methode für existierende Benutzer
     * - Überprüft, dass TRUE zurückgegeben wird, wenn der Benutzer in der DB existiert
     */
    public function testIsKnownUserReturnsTrueForExistingUser(): void
    {
        $user = $this->createUser('existingUser');

        $this->userRepository->expects($this->once())
            ->method('findByUsername')
            ->with('existingUser')
            ->willReturn($user);

        $result = $this->userValidator->isKnownUser('existingUser');

        $this->assertTrue($result);
    }

    /**
     * Testet die isKnownUser-Methode für nicht existierende Benutzer
     * - Überprüft, dass FALSE zurückgegeben wird, wenn der Benutzer nicht in der DB existiert
     */
    public function testIsKnownUserReturnsFalseForNonExistingUser(): void
    {
        $this->userRepository->expects($this->once())
            ->method('findByUsername')
            ->with('nonExistingUser')
            ->willReturn(null);

        $result = $this->userValidator->isKnownUser('nonExistingUser');

        $this->assertFalse($result);
    }

    /**
     * Testet die filterKnownAndUnknownUsers-Methode mit gemischten Datensätzen
     * - Überprüft, dass Datensätze korrekt in bekannte und unbekannte Benutzer aufgeteilt werden
     * - Überprüft, dass Datensätze ohne Username-Feld übersprungen werden
     */
    public function testFilterKnownAndUnknownUsersWithMixedRecords(): void
    {
        $records = [
            ['username' => 'known1', 'data' => 'some data 1'],
            ['username' => 'unknown1', 'data' => 'some data 2'],
            ['username' => 'known2', 'data' => 'some data 3'],
            ['username' => 'unknown2', 'data' => 'some data 4'],
            ['data' => 'record without username'] // Should be skipped
        ];

        $this->userRepository->method('findByUsername')
            ->willReturnCallback(function ($username) {
                return in_array($username, ['known1', 'known2']) ? $this->createUser($username) : null;
            });

        list($knownUsers, $unknownUsers) = $this->userValidator->filterKnownAndUnknownUsers($records);

        $expectedKnownUsers = [
            ['username' => 'known1', 'data' => 'some data 1'],
            ['username' => 'known2', 'data' => 'some data 3']
        ];

        $expectedUnknownUsers = [
            ['username' => 'unknown1', 'data' => 'some data 2'],
            ['username' => 'unknown2', 'data' => 'some data 4']
        ];

        $this->assertEquals($expectedKnownUsers, $knownUsers);
        $this->assertEquals($expectedUnknownUsers, $unknownUsers);
    }

    /**
     * Testet die filterKnownAndUnknownUsers-Methode mit einem leeren Array
     * - Überprüft, dass leere Arrays für bekannte und unbekannte Benutzer zurückgegeben werden
     */
    public function testFilterKnownAndUnknownUsersWithEmptyArray(): void
    {
        list($knownUsers, $unknownUsers) = $this->userValidator->filterKnownAndUnknownUsers([]);

        $this->assertEquals([], $knownUsers);
        $this->assertEquals([], $unknownUsers);
    }

    /**
     * Testet die filterKnownAndUnknownUsers-Methode mit Datensätzen ohne Username-Feld
     * - Überprüft, dass Datensätze ohne 'username'-Schlüssel korrekt übersprungen werden
     * - Überprüft, dass nur gültige Datensätze verarbeitet werden
     */
    public function testFilterKnownAndUnknownUsersSkipsRecordsWithoutUsername(): void
    {
        $records = [
            ['data' => 'record 1'],
            ['name' => 'not username'],
            ['username' => 'validUser', 'data' => 'valid record']
        ];

        $this->userRepository->expects($this->once())
            ->method('findByUsername')
            ->with('validUser')
            ->willReturn($this->createUser('validUser'));

        list($knownUsers, $unknownUsers) = $this->userValidator->filterKnownAndUnknownUsers($records);

        $expectedKnownUsers = [
            ['username' => 'validUser', 'data' => 'valid record']
        ];

        $this->assertEquals($expectedKnownUsers, $knownUsers);
        $this->assertEquals([], $unknownUsers);
    }

    /**
     * Testet die isValidUsername-Methode mit gültigen Benutzernamen
     * - Überprüft verschiedene erlaubte Formate: Buchstaben, Zahlen, Punkte, Unterstriche, Bindestriche, @-Zeichen
     * - Überprüft die erlaubte Länge von 2-50 Zeichen
     */
    public function testIsValidUsernameWithValidUsernames(): void
    {
        $validUsernames = [
            'user',
            'user123',
            'user.name',
            'user_name',
            'user-name',
            'user@domain',
            'a1',
            str_repeat('a', 50) // 50 characters
        ];

        foreach ($validUsernames as $username) {
            $result = $this->userValidator->isValidUsername($username);
            $this->assertTrue($result, "Username '$username' should be valid");
        }
    }

    /**
     * Testet die isValidUsername-Methode mit ungültigen Benutzernamen
     * - Überprüft Ablehnung von zu kurzen/langen Namen, Leerzeichen und Sonderzeichen
     * - Überprüft alle nicht erlaubten Zeichen und Formatfehler
     */
    public function testIsValidUsernameWithInvalidUsernames(): void
    {
        $invalidUsernames = [
            '',
            ' ',
            '   ',
            'a', // Too short (1 character)
            str_repeat('a', 51), // Too long (51 characters)
            'user name', // Space not allowed
            'user!', // Special character not allowed
            'user#', // Special character not allowed
            'user$', // Special character not allowed
            'user%', // Special character not allowed
            'user&', // Special character not allowed
            'user*', // Special character not allowed
            'user+', // Special character not allowed
            'user=', // Special character not allowed
            'user?', // Special character not allowed
            'user[', // Special character not allowed
            'user]', // Special character not allowed
            'user{', // Special character not allowed
            'user}', // Special character not allowed
            'user|', // Special character not allowed
            'user\\', // Special character not allowed
            'user/', // Special character not allowed
            'user<', // Special character not allowed
            'user>', // Special character not allowed
            'user,', // Special character not allowed
            'user;', // Special character not allowed
            'user:', // Special character not allowed
            'user"', // Special character not allowed
            "user'", // Special character not allowed
        ];

        foreach ($invalidUsernames as $username) {
            $result = $this->userValidator->isValidUsername($username);
            $this->assertFalse($result, "Username '$username' should be invalid");
        }
    }

    /**
     * Testet die isValidEmail-Methode mit gültigen E-Mail-Adressen
     * - Überprüft verschiedene erlaubte E-Mail-Formate und Domänen-Strukturen
     * - Überprüft spezielle Zeichen wie Punkte, Plus-Zeichen, Bindestriche in lokalen Teilen
     */
    public function testIsValidEmailWithValidEmails(): void
    {
        $validEmails = [
            'user@example.com',
            'user.name@example.com',
            'user+tag@example.com',
            'user123@example-domain.com',
            'user@sub.example.com',
            'a@b.co',
            'user@example.org',
            'user@example.net',
            'user_name@example.com',
            'user-name@example.com'
        ];

        foreach ($validEmails as $email) {
            $result = $this->userValidator->isValidEmail($email);
            $this->assertTrue($result, "Email '$email' should be valid");
        }
    }

    /**
     * Testet die isValidEmail-Methode mit ungültigen E-Mail-Adressen
     * - Überprüft Ablehnung von unvollständigen, falsch formatierten oder zu langen E-Mails
     * - Überprüft verschiedene Syntaxfehler und Edge Cases
     */
    public function testIsValidEmailWithInvalidEmails(): void
    {
        $invalidEmails = [
            '',
            'invalid',
            'invalid@',
            '@example.com',
            'invalid.email',
            'invalid@.com',
            'invalid@com',
            'invalid..email@example.com',
            'invalid@example.',
            'invalid@.example.com',
            str_repeat('a', 250) . '@example.com', // Too long (>254 characters)
        ];

        foreach ($invalidEmails as $email) {
            $result = $this->userValidator->isValidEmail($email);
            $this->assertFalse($result, "Email '$email' should be invalid");
        }
    }

    /**
     * Testet die isValidEmail-Methode mit der maximalen Längengrenze
     * - Überprüft, dass E-Mails bis 254 Zeichen akzeptiert werden
     * - Überprüft, dass E-Mails über 254 Zeichen abgelehnt werden
     */
    public function testIsValidEmailWithMaximumLength(): void
    {
        // Test a reasonably long but valid email
        $localPart = str_repeat('a', 50);
        $email = $localPart . '@example.com'; // 50 + 12 = 62 characters
        
        $result = $this->userValidator->isValidEmail($email);
        $this->assertTrue($result);

        // Test email over the 254 character limit
        $longLocalPart = str_repeat('a', 250);
        $longEmail = $longLocalPart . '@example.com'; // 250 + 12 = 262 characters
        
        $result = $this->userValidator->isValidEmail($longEmail);
        $this->assertFalse($result);
    }

    /**
     * Testet die korrekte Initialisierung des UserValidator-Konstruktors
     * - Überprüft, dass das UserRepository korrekt injiziert wird
     */
    public function testConstructorAcceptsUserRepository(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userValidator = new UserValidator($userRepository);

        $this->assertInstanceOf(UserValidator::class, $userValidator);
    }

    /**
     * Testet die identifyUnknownUsers-Methode mit Arrays, die verschiedene Werttypen enthalten
     * - Überprüft, dass die Methode korrekt funktioniert, auch wenn die Array-Werte keine Booleans sind
     * - Überprüft die Verarbeitung von Strings, Zahlen und null-Werten als Array-Werte
     */
    public function testIdentifyUnknownUsersHandlesArrayWithValues(): void
    {
        // Test with array where values are not boolean
        $usernames = [
            'user1' => 'some_value',
            'user2' => 123,
            'user3' => null
        ];

        $this->userRepository->expects($this->once())
            ->method('findMultipleByUsernames')
            ->with(['user1', 'user2', 'user3'])
            ->willReturn([]);

        $result = $this->userValidator->identifyUnknownUsers($usernames);

        $this->assertEquals(['user1', 'user2', 'user3'], $result);
    }

    /**
     * Test: Case-insensitive Erkennung von bekannten/unbekannten Benutzern
     * 
     * Simuliert das ursprüngliche Problem:
     * - User in DB: "svenmuller" (lowercase normalisiert)
     * - CSV enthält: "SvenMueller" (mixed case)
     * - Sollte als "bekannter Benutzer" erkannt werden
     */
    public function testFilterKnownAndUnknownUsersCaseInsensitive(): void
    {
        // CSV-Daten mit gemischter Schreibweise (wie sie in der Realität vorkamen)
        $csvRecords = [
            ['username' => 'SvenMueller', 'email' => 'sven@example.com'],      // DB: svenmueller
            ['username' => 'JOHNDOE', 'email' => 'john@example.com'],          // DB: johndoe
            ['username' => 'Jane.Doe', 'email' => 'jane@example.com'],         // DB: jane.doe
            ['username' => 'UnknownUser', 'email' => 'unknown@example.com'],   // existiert nicht
            ['username' => '  MaxMuster  ', 'email' => 'max@example.com']      // DB: maxmuster (mit Leerzeichen)
        ];

        // Mock: Repository gibt Users zurück die lowercase normalisiert sind
        $knownUsersInDb = [
            'svenmueller' => $this->createUser('svenmueller'),  // Das ist wichtig! CSV: SvenMueller -> svenmueller
            'johndoe' => $this->createUser('johndoe'),
            'jane.doe' => $this->createUser('jane.doe'),
            'maxmuster' => $this->createUser('maxmuster')
        ];

        // UserRepository mock konfigurieren für case-insensitive Suche
        // Da filterKnownAndUnknownUsers intern isKnownUser verwendet und das wiederum findByUsername
        $this->userRepository->method('findByUsername')
            ->willReturnCallback(function ($username) use ($knownUsersInDb) {
                // Simuliert die case-insensitive DB-Suche
                $normalizedSearch = strtolower(trim($username));
                return $knownUsersInDb[$normalizedSearch] ?? null;
            });

        list($knownUsers, $unknownUsers) = $this->userValidator->filterKnownAndUnknownUsers($csvRecords);

        // Erwartete Ergebnisse
        $expectedKnownUsers = [
            ['username' => 'SvenMueller', 'email' => 'sven@example.com'],
            ['username' => 'JOHNDOE', 'email' => 'john@example.com'],
            ['username' => 'Jane.Doe', 'email' => 'jane@example.com'],
            ['username' => '  MaxMuster  ', 'email' => 'max@example.com']
        ];

        $expectedUnknownUsers = [
            ['username' => 'UnknownUser', 'email' => 'unknown@example.com']
        ];

        $this->assertCount(4, $knownUsers, 'Sollte 4 bekannte Benutzer finden');
        $this->assertCount(1, $unknownUsers, 'Sollte 1 unbekannten Benutzer finden');
        
        $this->assertEquals($expectedKnownUsers, $knownUsers);
        $this->assertEquals($expectedUnknownUsers, $unknownUsers);
    }

    /**
     * Test: isKnownUser mit case-insensitive Suche
     */
    public function testIsKnownUserCaseInsensitive(): void
    {
        // Mock User mit normalisiertem Username
        $user = $this->createUser('svenmuller');

        // Repository soll case-insensitive suchen
        $this->userRepository->method('findByUsername')
            ->willReturnCallback(function ($username) use ($user) {
                // Simuliert case-insensitive DB-Suche
                $normalized = strtolower(trim($username));
                return $normalized === 'svenmuller' ? $user : null;
            });

        // Test verschiedene Schreibweisen
        $this->assertTrue($this->userValidator->isKnownUser('svenmuller'));
        $this->assertTrue($this->userValidator->isKnownUser('SvenMuller'));
        $this->assertTrue($this->userValidator->isKnownUser('SVENMULLER'));
        $this->assertTrue($this->userValidator->isKnownUser('sVeNmUlLeR'));
        $this->assertTrue($this->userValidator->isKnownUser('  SvenMuller  '));
        
        // Nicht existierender User
        $this->assertFalse($this->userValidator->isKnownUser('nonexistent'));
    }

    /**
     * Test: identifyUnknownUsers mit case-insensitive Logik
     * 
     * Testet die Methode die in CSV-Imports verwendet wird um
     * unbekannte Benutzer zu identifizieren
     */
    public function testFindUnknownUsersCaseInsensitive(): void
    {
        // CSV-Style Daten mit verschiedenen Schreibweisen
        $csvUsernames = [
            'SvenMueller',      // bekannt (DB: svenmueller)
            'JOHNDOE',          // bekannt (DB: johndoe)  
            'UnknownUser',      // unbekannt
            '  JaneDoe  '       // bekannt (DB: janedoe)
        ];

        // Mock DB Users (normalisiert)
        $knownUsersInDb = [
            'svenmueller' => $this->createUser('svenmueller'),
            'johndoe' => $this->createUser('johndoe'),
            'janedoe' => $this->createUser('janedoe')
        ];

        $this->userRepository->method('findMultipleByUsernames')
            ->willReturnCallback(function (array $usernames) use ($knownUsersInDb) {
                $results = [];
                foreach ($usernames as $username) {
                    $normalized = strtolower(trim($username));
                    if (isset($knownUsersInDb[$normalized])) {
                        $results[] = $knownUsersInDb[$normalized];
                    }
                }
                return $results;
            });

        // identifyUnknownUsers erwartet ein assoziatives Array, nicht ein simples Array
        $csvData = array_flip($csvUsernames);  // ['SvenMueller' => 0, 'JOHNDOE' => 1, ...]
        $unknownUsers = $this->userValidator->identifyUnknownUsers($csvData);

        $this->assertCount(1, $unknownUsers, 'Sollte nur 1 unbekannten Benutzer finden');
        $this->assertEquals(['UnknownUser'], $unknownUsers);
    }

    /**
     * Hilfsmethode zum Erstellen eines Mock-User-Objekts
     * 
     * @param string $username Der Benutzername für den Mock-User
     * @return User Mock-User-Objekt
     */
    private function createUser(string $username): User
    {
        $user = $this->createMock(User::class);
        $user->method('getUsername')->willReturn(\App\ValueObject\Username::fromString($username));
        return $user;
    }
}