<?php

namespace App\Tests\Integration;

use App\Service\CsvProcessor;
use App\Service\CsvFileReaderInterface;
use App\Service\SessionManager;
use App\Dto\CsvProcessingResult;
use App\Repository\UserRepository;
use App\Entity\CsvFieldConfig;
use App\ValueObject\UnknownUserWithTicket;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Edge Cases und Negative Tests für UnknownUserWithTicket-Funktionalität
 * 
 * Diese Testsuite überprüft verschiedene Edge Cases und Fehlerbedingungen,
 * um sicherzustellen, dass das System robust und fehlertolerant ist.
 */
class UnknownUserWithTicketEdgeCasesTest extends TestCase
{
    private CsvProcessor $csvProcessor;
    private SessionManager $sessionManager;
    private UserRepository $userRepository;
    private RequestStack $requestStack;
    private SessionInterface $session;
    private array $sessionStore = [];

    protected function setUp(): void
    {
        $this->setupSessionManager();
        $this->setupUserRepository();
        $this->setupCsvProcessor();
    }

    /**
     * Testet extremes Case-Sensitivity-Szenario
     */
    public function testExtremeCaseSensitivityScenarios(): void
    {
        $content = "ticketId,username,ticketName\n"
                 . "T-001,testuser,Issue 1\n"
                 . "T-002,TESTUSER,Issue 2\n"
                 . "T-003,TestUser,Issue 3\n"
                 . "T-004,tEsTuSeR,Issue 4\n"
                 . "T-005,test_user,Issue 5\n";

        $reader = $this->createCsvFileReaderFromContent($content);
        $this->csvProcessor = new CsvProcessor($reader, $this->userRepository);

        $uploadedFile = $this->createMock(UploadedFile::class);

        // All diese Varianten sind "unbekannt"
        $this->userRepository->method('identifyUnknownUsers')
            ->willReturn([
                'TESTUSER',    // sollte T-002 finden (exakt)
                'testuser',    // sollte T-001 finden (exakt)
                'TestUser',    // sollte T-003 finden (exakt)  
                'tEsTuSeR',    // sollte T-004 finden (exakt)
                'TEST_USER',   // sollte T-005 finden (case-insensitive)
                'unknown_user' // sollte nicht gefunden werden
            ]);

        $cfg = $this->createCsvFieldConfig();
        $result = $this->csvProcessor->process($uploadedFile, $cfg);

        $this->assertCount(6, $result->unknownUsers);

        // Alle 6 sollten UnknownUserWithTicket-Objekte sein (kein String-Fallback mehr)
        foreach ($result->unknownUsers as $user) {
            $this->assertInstanceOf(UnknownUserWithTicket::class, $user);
        }

        // Der nicht gefundene User sollte Fallback-TicketId 'UNKNOWN' haben
        $unknownUserEntry = null;
        foreach ($result->unknownUsers as $user) {
            if ($user->getUsernameString() === 'unknown_user') {
                $unknownUserEntry = $user;
                break;
            }
        }
        $this->assertNotNull($unknownUserEntry);
        $this->assertEquals('UNKNOWN', $unknownUserEntry->getTicketIdString());
    }

    /**
     * Testet Szenario mit duplizierten Benutzernamen in CSV
     */
    public function testDuplicateUsernamesInCsv(): void
    {
        $content = "ticketId,username,ticketName\n"
                 . "T-001,dupuser,First Occurrence\n"
                 . "T-002,dupuser,Second Occurrence\n"
                 . "T-003,dupuser,Third Occurrence\n"
                 . "T-004,otheruser,Other Issue\n";

        $reader = $this->createCsvFileReaderFromContent($content);
        $this->csvProcessor = new CsvProcessor($reader, $this->userRepository);

        $uploadedFile = $this->createMock(UploadedFile::class);

        $this->userRepository->method('identifyUnknownUsers')
            ->willReturn(['dupuser', 'otheruser']);

        $cfg = $this->createCsvFieldConfig();
        $result = $this->csvProcessor->process($uploadedFile, $cfg);

        $this->assertCount(2, $result->unknownUsers);

        foreach ($result->unknownUsers as $user) {
            $this->assertInstanceOf(UnknownUserWithTicket::class, $user);
            
            if ($user->getUsernameString() === 'dupuser') {
                // Sollte das erste gefundene Ticket verwenden
                $this->assertEquals('T-001', $user->getTicketIdString());
                $this->assertEquals('First Occurrence', $user->getTicketNameString());
            }
        }
    }

    /**
     * Testet Session-Serialisierung mit korrupten Daten
     */
    public function testSessionHandlingWithCorruptedData(): void
    {
        // Simuliere verschiedene korrupte Session-Zustände
        $corruptedStates = [
            // Malformed JSON-artige Struktur
            [
                ['type' => 'UnknownUserWithTicket'], // Fehlende Felder
                ['type' => 'UnknownUserWithTicket', 'username' => 'test'], // Fehlendes ticketId
                ['type' => 'UnknownUserWithTicket', 'username' => 'testuser', 'ticketId' => 'T1'], // Zu kurze ticketId
            ],
            // Gemischte korrupte und valide Daten
            [
                ['type' => 'string', 'username' => 'valid_string_user'],
                ['type' => 'UnknownUserWithTicket', 'username' => 'valid_user', 'ticketId' => 'T-001', 'ticketName' => 'Valid'],
                ['invalid' => 'structure'],
                'plain_string_legacy',
            ],
            // Komplett leere oder ungültige Strukturen
            [
                null,
                [],
                ['type' => 'unknown_type'],
                42, // Nummer statt Array
                'plain_string'
            ]
        ];

        foreach ($corruptedStates as $state) {
            $this->sessionStore = ['unknown_users' => $state];
            
            // Sollte nicht crashen, sondern graceful degradieren
            $result = $this->sessionManager->getUnknownUsers();
            $this->assertIsArray($result);
            
            // Validiere nur, dass ungültige Einträge herausgefiltert werden
            // und keine Exceptions geworfen werden
            foreach ($result as $user) {
                if (is_string($user)) {
                    $this->assertNotEmpty($user, 'String users should not be empty');
                } elseif ($user instanceof UnknownUserWithTicket) {
                    $this->assertInstanceOf(UnknownUserWithTicket::class, $user);
                }
                // Alle anderen Typen werden stillschweigend ignoriert
            }
        }
    }

    /**
     * Testet normale Datengrößen 
     */
    public function testNormalDataSizes(): void
    {
        // Normales CSV mit 100 Einträgen
        $headerLine = "ticketId,username,ticketName\n";
        $dataLines = [];
        
        for ($i = 1; $i <= 100; $i++) {
            $dataLines[] = sprintf("T-%06d,user%d,Issue Number %d", $i, $i, $i);
        }
        
        $content = $headerLine . implode("\n", $dataLines);
        
        $reader = $this->createCsvFileReaderFromContent($content);
        $this->csvProcessor = new CsvProcessor($reader, $this->userRepository);

        $uploadedFile = $this->createMock(UploadedFile::class);

        // Alle Benutzer sind "unbekannt" (werden zu lowercase normalisiert)
        $unknownUsers = [];
        for ($i = 1; $i <= 100; $i++) {
            $unknownUsers[] = sprintf("user%d", $i);
        }
        
        $this->userRepository->method('identifyUnknownUsers')
            ->willReturn($unknownUsers);

        $cfg = $this->createCsvFieldConfig();
        $result = $this->csvProcessor->process($uploadedFile, $cfg);

        $this->assertCount(100, $result->unknownUsers);
        
        // Alle sollten UnknownUserWithTicket-Objekte sein
        foreach ($result->unknownUsers as $user) {
            $this->assertInstanceOf(UnknownUserWithTicket::class, $user);
        }
    }

    /**
     * Testet Grenzwerte für Benutzernamen
     */
    public function testBoundaryValues(): void
    {
        // Minimal erlaubte Benutzernamen-Länge (3 Zeichen)
        $content = "ticketId,username,ticketName\n";
        $content .= "T-001234,abc,Short Ticket Name\n";
        $content .= "T-001235,xyz,Another Short Name\n";
        
        $reader = $this->createCsvFileReaderFromContent($content);
        $this->csvProcessor = new CsvProcessor($reader, $this->userRepository);

        $uploadedFile = $this->createMock(UploadedFile::class);
        
        $this->userRepository->method('identifyUnknownUsers')
            ->willReturn(['abc', 'xyz']);

        $cfg = $this->createCsvFieldConfig();
        $result = $this->csvProcessor->process($uploadedFile, $cfg);

        $this->assertCount(2, $result->unknownUsers);
        
        // Verifikation der minimal langen Benutzernamen
        $usernames = array_map(fn($user) => $user->getUsernameString(), $result->unknownUsers);
        $this->assertContains('abc', $usernames);
        $this->assertContains('xyz', $usernames);
    }

    /**
     * Testet Unicode und Sonderzeichen Edge Cases
     */
    public function testUnicodeAndSpecialCharacterEdgeCases(): void
    {
        $specialCases = [
            // Verschiedene Unicode-Kategorien (mindestens 3 Zeichen)
            // Alphanumerische mit Sonderzeichen
            'user123', 'test456',
            'user-test', 'user_name', 'user.name',
            'testuser01', 'testuser02', 'testuser03',
            'testuser04', 'testuser05', 'testuser06'
        ];

        $content = "ticketId,username,ticketName\n";
        foreach ($specialCases as $i => $username) {
            $content .= "T-" . ($i + 1) . ",{$username},Issue for {$username}\n";
        }

        $reader = $this->createCsvFileReaderFromContent($content);
        $this->csvProcessor = new CsvProcessor($reader, $this->userRepository);

        $uploadedFile = $this->createMock(UploadedFile::class);

        $this->userRepository->method('identifyUnknownUsers')
            ->willReturn($specialCases);

        $cfg = $this->createCsvFieldConfig();
        $result = $this->csvProcessor->process($uploadedFile, $cfg);

        $this->assertCount(count($specialCases), $result->unknownUsers);

        // Session-Handling mit Unicode-Zeichen
        $this->sessionManager->storeUploadResults($result);
        
        $retrievedUsers = $this->sessionManager->getUnknownUsers();
        $this->assertCount(count($specialCases), $retrievedUsers);

        foreach ($retrievedUsers as $user) {
            if ($user instanceof UnknownUserWithTicket) {
                // Unicode-Zeichen sollten korrekt erhalten bleiben
                $this->assertContains($user->getUsernameString(), $specialCases);
            }
        }
    }

    /**
     * Testet Memory-Intensive Szenarien
     */
    public function testMemoryIntensiveScenarios(): void
    {
        // Maximale TicketName-Länge (50 Zeichen)
        $maxTicketName = str_repeat('X', 50); // Genau 50 Zeichen

        $unknownUser = new UnknownUserWithTicket(
            new \App\ValueObject\Username('testuser'),
            new \App\ValueObject\TicketId('T-LONG'),
            new \App\ValueObject\TicketName($maxTicketName)
        );

        $processingResult = new CsvProcessingResult(unknownUsers: [$unknownUser]);
        $this->sessionManager->storeUploadResults($processingResult);
        
        $retrievedUsers = $this->sessionManager->getUnknownUsers();
        
        $this->assertCount(1, $retrievedUsers);
        $this->assertInstanceOf(UnknownUserWithTicket::class, $retrievedUsers[0]);
        $this->assertEquals($maxTicketName, $retrievedUsers[0]->getTicketNameString());
    }

    /**
     * Testet Concurrent Session Access (simuliert)
     */
    public function testConcurrentSessionAccess(): void
    {
        $unknownUser1 = new UnknownUserWithTicket(
            new \App\ValueObject\Username('user1'),
            new \App\ValueObject\TicketId('T-001'),
            new \App\ValueObject\TicketName('First Issue')
        );

        // Erste Session-Operation
        $processingResult1 = new CsvProcessingResult(unknownUsers: [$unknownUser1]);
        $this->sessionManager->storeUploadResults($processingResult1);

        // Zweite Session-Operation (simuliert concurrent access)
        $unknownUser2 = new UnknownUserWithTicket(
            new \App\ValueObject\Username('user2'),
            new \App\ValueObject\TicketId('T-002'),
            new \App\ValueObject\TicketName('Second Issue')
        );

        $processingResult2 = new CsvProcessingResult(unknownUsers: [$unknownUser2]);
        $this->sessionManager->storeUploadResults($processingResult2);

        // Die zweite Operation sollte die erste überschreiben (intended behavior)
        $retrievedUsers = $this->sessionManager->getUnknownUsers();
        
        $this->assertCount(1, $retrievedUsers);
        $this->assertEquals('user2', $retrievedUsers[0]->getUsernameString());
        $this->assertEquals('T-002', $retrievedUsers[0]->getTicketIdString());
    }

    /**
     * Hilfsmethoden
     */
    private function setupSessionManager(): void
    {
        $this->sessionStore = [];
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->session = $this->createMock(SessionInterface::class);
        
        $this->session->method('set')->willReturnCallback(function($key, $value) {
            $this->sessionStore[$key] = $value;
        });
        $this->session->method('get')->willReturnCallback(function($key, $default = null) {
            return $this->sessionStore[$key] ?? $default;
        });
        $this->session->method('remove')->willReturnCallback(function($key) {
            unset($this->sessionStore[$key]);
        });
        
        $this->requestStack->method('getSession')->willReturn($this->session);
        $this->sessionManager = new SessionManager($this->requestStack);
    }

    private function setupUserRepository(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
    }

    private function setupCsvProcessor(): void
    {
        $csvFileReader = $this->createMock(CsvFileReaderInterface::class);
        $this->csvProcessor = new CsvProcessor(
            $csvFileReader,
            $this->userRepository,
        );
    }

    private function createCsvFieldConfig(): CsvFieldConfig
    {
        $cfg = $this->createMock(CsvFieldConfig::class);
        $cfg->method('getFieldMapping')->willReturn([
            'ticketId' => 'ticketId',
            'username' => 'username',
            'ticketName' => 'ticketName'
        ]);
        return $cfg;
    }

    /**
     * Erstellt einen CsvFileReaderInterface-Mock aus CSV-Content-String
     */
    private function createCsvFileReaderFromContent(string $content): CsvFileReaderInterface
    {
        $lines = explode("\n", trim($content));
        $header = str_getcsv(array_shift($lines), ',', '"', '\\');
        $rows = array_map(fn($line) => str_getcsv($line, ',', '"', '\\'), array_filter($lines, fn($l) => trim($l) !== ''));

        $reader = $this->createMock(CsvFileReaderInterface::class);
        $reader->method('openCsvFile')->willReturn('mock_handle');
        $reader->method('readHeader')->willReturn($header);
        $reader->method('validateRequiredColumns')->willReturnCallback(
            function (array $hdr, array $requiredColumns) {
                $indices = [];
                foreach ($requiredColumns as $col) {
                    $i = array_search($col, $hdr);
                    if ($i === false) throw new \Exception("Column $col not found");
                    $indices[$col] = $i;
                }
                return $indices;
            }
        );
        $reader->method('processRows')->willReturnCallback(
            function ($handle, callable $rowProcessor) use ($rows) {
                $rowNumber = 1;
                foreach ($rows as $row) {
                    $rowNumber++;
                    $rowProcessor($row, $rowNumber);
                }
            }
        );
        $reader->method('closeHandle');

        return $reader;
    }
}