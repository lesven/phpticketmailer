<?php

namespace App\Tests\Service;

use App\Service\CsvProcessor;
use App\Service\CsvFileReader;
use App\Repository\UserRepository;
use App\Entity\CsvFieldConfig;
use App\ValueObject\UnknownUserWithTicket;
use PHPUnit\Framework\TestCase;
use App\ValueObject\TicketData;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Tests für case-insensitive Username-Matching im CsvProcessor
 * 
 * Diese Tests überprüfen, dass unbekannte Benutzer korrekt mit Tickets gematched werden,
 * auch wenn die Groß-/Kleinschreibung unterschiedlich ist.
 */
class CsvProcessorCaseInsensitiveTest extends TestCase
{
    private CsvProcessor $processor;
    private CsvFileReader $csvFileReader;
    private UserRepository $userRepository;
    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->csvFileReader = $this->createTestCsvFileReader();
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->requestStack = $this->createTestRequestStack();
        
        $this->processor = new CsvProcessor(
            $this->csvFileReader,
            $this->userRepository,
            $this->requestStack
        );
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
        
        $uploadedFile = $this->createTempCsvFile($content);
        
        // Unknown users mit verschiedener Groß-/Kleinschreibung
        $this->userRepository->method('identifyUnknownUsers')
            ->willReturn(['johndoe', 'janedoe', 'mixedcase']); // Normalisiert zu lowercase

        $cfg = $this->createCsvFieldConfig();
        $result = $this->processor->process($uploadedFile, $cfg);

        // Alle 3 unknown users sollten als UnknownUserWithTicket-Objekte erstellt werden
        $this->assertCount(3, $result->getUnknownUsers());
        
        foreach ($result->getUnknownUsers() as $unknownUser) {
            $this->assertInstanceOf(UnknownUserWithTicket::class, $unknownUser);
        }

        // Spezifische Checks für jeden Benutzer
        $usernames = array_map(fn($u) => $u->getUsernameString(), $result->getUnknownUsers());
        $this->assertContains('johndoe', $usernames);
        $this->assertContains('janedoe', $usernames);
        $this->assertContains('mixedcase', $usernames);
    }

    /**
     * Testet Fallback zu String wenn kein Ticket gefunden wird
     */
    public function testFallbackToStringWhenNoTicketFound(): void
    {
        $content = "ticketId,username,ticketName\n"
                 . "T-001,existinguser,Test Issue\n";
        
        $uploadedFile = $this->createTempCsvFile($content);
        
        // Ein User existiert in CSV, einer nicht
        $this->userRepository->method('identifyUnknownUsers')
            ->willReturn(['existinguser', 'nonexistentuser']);

        $cfg = $this->createCsvFieldConfig();
        $result = $this->processor->process($uploadedFile, $cfg);

        $this->assertCount(2, $result->getUnknownUsers());
        
        // Erster sollte UnknownUserWithTicket sein
        $this->assertInstanceOf(UnknownUserWithTicket::class, $result->getUnknownUsers()[0]);
        $this->assertEquals('existinguser', $result->getUnknownUsers()[0]->getUsernameString());
        
        // Zweiter sollte String-Fallback sein
        $this->assertIsString($result->getUnknownUsers()[1]);
        $this->assertEquals('nonexistentuser', $result->getUnknownUsers()[1]);
    }

    /**
     * Testet Edge Case: Leere Username-Liste
     */
    public function testEmptyUnknownUsers(): void
    {
        $content = "ticketId,username,ticketName\nT-001,user1,Test Issue\n";
        $uploadedFile = $this->createTempCsvFile($content);
        
        $this->userRepository->method('identifyUnknownUsers')
            ->willReturn([]);

        $cfg = $this->createCsvFieldConfig();
        $result = $this->processor->process($uploadedFile, $cfg);

        $this->assertEmpty($result->getUnknownUsers());
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
        
        $uploadedFile = $this->createTempCsvFile($content);
        
        $this->userRepository->method('identifyUnknownUsers')
            ->willReturn(['testuser']); // Username wird normalisiert

        $cfg = $this->createCsvFieldConfig();
        $result = $this->processor->process($uploadedFile, $cfg);

        // Sollte nur einen unknown user geben
        $this->assertCount(1, $result->getUnknownUsers());
        $this->assertInstanceOf(UnknownUserWithTicket::class, $result->getUnknownUsers()[0]);
        $this->assertEquals('testuser', $result->getUnknownUsers()[0]->getUsernameString());
        
        // Das erste gefundene Ticket sollte verwendet werden
        $this->assertEquals('T-001', $result->getUnknownUsers()[0]->getTicketIdString());
        $this->assertEquals('First Issue', $result->getUnknownUsers()[0]->getTicketNameString());
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
        
        $uploadedFile = $this->createTempCsvFile($content);
        
        $this->userRepository->method('identifyUnknownUsers')
            ->willReturn(['User.Name', 'USER-NAME', 'user_name']);

        $cfg = $this->createCsvFieldConfig();
        $result = $this->processor->process($uploadedFile, $cfg);

        $this->assertCount(3, $result->getUnknownUsers());
        
        foreach ($result->getUnknownUsers() as $unknownUser) {
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
        
        $uploadedFile = $this->createTempCsvFile($content);
        
        $this->userRepository->method('identifyUnknownUsers')
            ->willReturn(['test-user', 'user_name', 'user123']);

        $cfg = $this->createCsvFieldConfig();
        $result = $this->processor->process($uploadedFile, $cfg);

        $this->assertCount(3, $result->getUnknownUsers());
        
        foreach ($result->getUnknownUsers() as $unknownUser) {
            $this->assertInstanceOf(UnknownUserWithTicket::class, $unknownUser);
        }
    }

    private function createTempCsvFile(string $content): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'csv_test');
        file_put_contents($tmp, $content);
        return new UploadedFile($tmp, 'test.csv', null, null, true);
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

    private function createTestCsvFileReader(): CsvFileReader
    {
        return new class extends CsvFileReader {
            public function __construct() { parent::__construct(',', 1000); }
            
            public function openCsvFile($file) {
                $path = $file instanceof UploadedFile ? $file->getPathname() : $file;
                return fopen($path, 'r');
            }
            
            public function readHeader($handle): array {
                return fgetcsv($handle, 1000, ',', '"', '\\');
            }
            
            public function validateRequiredColumns(array $header, array $requiredColumns): array {
                $indices = [];
                foreach ($requiredColumns as $col) {
                    $i = array_search($col, $header);
                    if ($i === false) throw new \Exception("Column $col not found");
                    $indices[$col] = $i;
                }
                return $indices;
            }
            
            public function processRows($handle, callable $rowProcessor): void {
                $rowNumber = 1;
                while (($row = fgetcsv($handle, 1000, ',', '"', '\\')) !== false) {
                    $rowNumber++;
                    $rowProcessor($row, $rowNumber);
                }
            }
            
            public function closeHandle($handle): void {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            }
        };
    }

    private function createTestRequestStack(): RequestStack
    {
        $requestStack = $this->createMock(RequestStack::class);
        $store = [];
        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock->method('set')->willReturnCallback(function($k, $v) use (&$store) {
            $store[$k] = $v;
        });
        $sessionMock->method('get')->willReturnCallback(function($k) use (&$store) {
            return $store[$k] ?? null;
        });
        $requestStack->method('getSession')->willReturn($sessionMock);
        return $requestStack;
    }
}