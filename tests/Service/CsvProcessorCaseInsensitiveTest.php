<?php

namespace App\Tests\Service;

use App\Service\CsvProcessor;
use App\Service\CsvFileReaderInterface;
use App\Dto\CsvProcessingResult;
use App\Repository\UserRepository;
use App\Entity\CsvFieldConfig;
use App\ValueObject\UnknownUserWithTicket;
use PHPUnit\Framework\TestCase;
use App\ValueObject\TicketData;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Tests für case-insensitive Username-Matching im CsvProcessor
 * 
 * Diese Tests überprüfen, dass unbekannte Benutzer korrekt mit Tickets gematched werden,
 * auch wenn die Groß-/Kleinschreibung unterschiedlich ist.
 */
class CsvProcessorCaseInsensitiveTest extends TestCase
{
    private CsvProcessor $processor;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
    }

    /**
     * Testet case-insensitive Username-Matching
     * Unknown user: "JohnDoe" -> Ticket username: "johndoe"
     */
    public function testCaseInsensitiveUsernameMatching(): void
    {
        // CSV mit unterschiedlicher Groß-/Kleinschreibung
        $content = "ticketId,username,ticketName\n"
                 . "T-001,johndoe,Test Issue 1\n"
                 . "T-002,JANEDOE,Test Issue 2\n"
                 . "T-003,MiXeDcAsE,Test Issue 3\n";
        
        $reader = $this->createCsvFileReaderFromContent($content);
        $this->processor = new CsvProcessor($reader, $this->userRepository);
        
        $uploadedFile = $this->createMock(UploadedFile::class);
        
        // Unknown users mit verschiedener Groß-/Kleinschreibung
        $this->userRepository->method('identifyUnknownUsers')
            ->willReturn(['johndoe', 'janedoe', 'mixedcase']); // Normalisiert zu lowercase

        $cfg = $this->createCsvFieldConfig();
        $result = $this->processor->process($uploadedFile, $cfg);

        // Alle 3 unknown users sollten als UnknownUserWithTicket-Objekte erstellt werden
        $this->assertCount(3, $result->unknownUsers);
        
        foreach ($result->unknownUsers as $unknownUser) {
            $this->assertInstanceOf(UnknownUserWithTicket::class, $unknownUser);
        }

        // Spezifische Checks für jeden Benutzer
        $usernames = array_map(fn($u) => $u->getUsernameString(), $result->unknownUsers);
        $this->assertContains('johndoe', $usernames);
        $this->assertContains('janedoe', $usernames);
        $this->assertContains('mixedcase', $usernames);
    }

    /**
     * Testet dass bei fehlendem Ticket ein UnknownUserWithTicket mit Fallback-TicketId erstellt wird
     */
    public function testFallbackWhenNoTicketFound(): void
    {
        $content = "ticketId,username,ticketName\n"
                 . "T-001,existinguser,Test Issue\n";
        
        $reader = $this->createCsvFileReaderFromContent($content);
        $this->processor = new CsvProcessor($reader, $this->userRepository);
        
        $uploadedFile = $this->createMock(UploadedFile::class);
        
        // Ein User existiert in CSV, einer nicht
        $this->userRepository->method('identifyUnknownUsers')
            ->willReturn(['existinguser', 'nonexistentuser']);

        $cfg = $this->createCsvFieldConfig();
        $result = $this->processor->process($uploadedFile, $cfg);

        $this->assertCount(2, $result->unknownUsers);
        
        // Beide sollten UnknownUserWithTicket sein
        $this->assertInstanceOf(UnknownUserWithTicket::class, $result->unknownUsers[0]);
        $this->assertEquals('existinguser', $result->unknownUsers[0]->getUsernameString());
        
        // Zweiter hat Fallback-TicketId
        $this->assertInstanceOf(UnknownUserWithTicket::class, $result->unknownUsers[1]);
        $this->assertEquals('nonexistentuser', $result->unknownUsers[1]->getUsernameString());
        $this->assertEquals('UNKNOWN', $result->unknownUsers[1]->getTicketIdString());
    }

    /**
     * Testet Edge Case: Leere Username-Liste
     */
    public function testEmptyUnknownUsers(): void
    {
        $content = "ticketId,username,ticketName\nT-001,user1,Test Issue\n";
        
        $reader = $this->createCsvFileReaderFromContent($content);
        $this->processor = new CsvProcessor($reader, $this->userRepository);
        
        $uploadedFile = $this->createMock(UploadedFile::class);
        
        $this->userRepository->method('identifyUnknownUsers')
            ->willReturn([]);

        $cfg = $this->createCsvFieldConfig();
        $result = $this->processor->process($uploadedFile, $cfg);

        $this->assertEmpty($result->unknownUsers);
    }

    /**
     * Testet Edge Case: Duplikate in verschiedenen Cases
     */
    public function testDuplicateUsernamesWithDifferentCases(): void
    {
        $content = "ticketId,username,ticketName\n"
                 . "T-001,testuser,First Issue\n"
                 . "T-002,TESTUSER,Second Issue\n"
                 . "T-003,TestUser,Third Issue\n";
        
        $reader = $this->createCsvFileReaderFromContent($content);
        $this->processor = new CsvProcessor($reader, $this->userRepository);
        
        $uploadedFile = $this->createMock(UploadedFile::class);
        
        $this->userRepository->method('identifyUnknownUsers')
            ->willReturn(['testuser']); // Username wird normalisiert

        $cfg = $this->createCsvFieldConfig();
        $result = $this->processor->process($uploadedFile, $cfg);

        // Sollte nur einen unknown user geben
        $this->assertCount(1, $result->unknownUsers);
        $this->assertInstanceOf(UnknownUserWithTicket::class, $result->unknownUsers[0]);
        $this->assertEquals('testuser', $result->unknownUsers[0]->getUsernameString());
        
        // Das erste gefundene Ticket sollte verwendet werden
        $this->assertEquals('T-001', $result->unknownUsers[0]->getTicketIdString());
        $this->assertEquals('First Issue', $result->unknownUsers[0]->getTicketNameString());
    }

    /**
     * Testet Edge Case: Username mit Sonderzeichen
     */
    public function testUsernameWithSpecialCharacters(): void
    {
        $content = "ticketId,username,ticketName\n"
                 . "T-001,user.name,Test Issue\n"
                 . "T-002,user-name,Test Issue 2\n"
                 . "T-003,user_name,Test Issue 3\n";
        
        $reader = $this->createCsvFileReaderFromContent($content);
        $this->processor = new CsvProcessor($reader, $this->userRepository);
        
        $uploadedFile = $this->createMock(UploadedFile::class);
        
        $this->userRepository->method('identifyUnknownUsers')
            ->willReturn(['User.Name', 'USER-NAME', 'user_name']);

        $cfg = $this->createCsvFieldConfig();
        $result = $this->processor->process($uploadedFile, $cfg);

        $this->assertCount(3, $result->unknownUsers);
        
        foreach ($result->unknownUsers as $unknownUser) {
            $this->assertInstanceOf(UnknownUserWithTicket::class, $unknownUser);
        }
    }

    /**
     * Testet mit gültigen Zeichen
     */
    public function testUsernameWithValidCharacters(): void
    {
        $content = "ticketId,username,ticketName\n"
                 . "T-001,test-user,Test Issue\n"
                 . "T-002,user_name,Test Issue 2\n"
                 . "T-003,user123,Test Issue 3\n";
        
        $reader = $this->createCsvFileReaderFromContent($content);
        $this->processor = new CsvProcessor($reader, $this->userRepository);
        
        $uploadedFile = $this->createMock(UploadedFile::class);
        
        $this->userRepository->method('identifyUnknownUsers')
            ->willReturn(['test-user', 'user_name', 'user123']);

        $cfg = $this->createCsvFieldConfig();
        $result = $this->processor->process($uploadedFile, $cfg);

        $this->assertCount(3, $result->unknownUsers);
        
        foreach ($result->unknownUsers as $unknownUser) {
            $this->assertInstanceOf(UnknownUserWithTicket::class, $unknownUser);
        }
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
        // Parse the content
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