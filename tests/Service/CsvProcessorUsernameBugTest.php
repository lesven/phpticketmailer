<?php

namespace App\Tests\Service;

use App\Service\CsvProcessor;
use App\Service\CsvFileReaderInterface;
use App\Dto\CsvProcessingResult;
use App\Repository\UserRepository;
use App\Entity\CsvFieldConfig;
use App\ValueObject\UnknownUserWithTicket;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CsvProcessorUsernameBugTest extends TestCase
{
    private CsvProcessor $csvProcessor;
    private CsvFileReaderInterface $csvFileReader;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->csvFileReader = $this->createMock(CsvFileReaderInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        
        $this->csvProcessor = new CsvProcessor(
            $this->csvFileReader,
            $this->userRepository,
        );
    }

    /**
     * Test für das ursprüngliche Problem: Username wird zu uniqueUsernames hinzugefügt,
     * auch wenn createTicketFromRow fehlschlägt
     */
    public function testUsernameNotAddedWhenTicketCreationFails(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $csvFieldConfig = new CsvFieldConfig();
        
        // Mock CSV-Reader Setup
        $header = ['Vorgangsschlüssel', 'Autor', 'Zusammenfassung'];
        $columnIndices = ['Vorgangsschlüssel' => 0, 'Autor' => 1, 'Zusammenfassung' => 2];
        
        $this->csvFileReader->method('openCsvFile')->willReturn('mock_handle');
        $this->csvFileReader->method('readHeader')->willReturn($header);
        $this->csvFileReader->method('validateRequiredColumns')->willReturn($columnIndices);
        
        // Simuliere eine Zeile mit ungültiger TicketId aber gültigem Username
        $rows = [
            ['TICKET-<script>', 'valid.username', 'Valid Ticket Name'] // Ungültige TicketId mit XSS-Pattern
        ];
        
        $this->csvFileReader->method('processRows')
            ->willReturnCallback(function ($handle, $callback) use ($rows) {
                foreach ($rows as $index => $row) {
                    $callback($row, $index + 2); // +2 weil Header Zeile 1 ist
                }
            });
        $this->csvFileReader->method('closeHandle');
        
        // UserRepository sollte KEINE Benutzer als unbekannt identifizieren,
        // da der Username nicht zu uniqueUsernames hinzugefügt werden sollte
        $this->userRepository->expects($this->once())
            ->method('identifyUnknownUsers')
            ->with([]) // Leeres Array erwartet!
            ->willReturn([]);
        
        $result = $this->csvProcessor->process($file, $csvFieldConfig);
        
        // Assertions
        $this->assertEmpty($result->validTickets, 'Keine gültigen Tickets erwartet');
        $this->assertCount(1, $result->invalidRows, 'Eine ungültige Zeile erwartet');
        $this->assertEmpty($result->unknownUsers, 'Keine unbekannten User erwartet');
        
        // Die ungültige Zeile sollte einen Fehler haben
        $this->assertArrayHasKey('error', $result->invalidRows[0]);
        $this->assertStringContainsString('Ticket ID contains invalid characters', $result->invalidRows[0]['error']);
    }

    /**
     * Test für den korrekten Fall: Username wird nur hinzugefügt wenn Ticket erfolgreich erstellt wurde
     */
    public function testUsernameAddedWhenTicketCreationSucceeds(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $csvFieldConfig = new CsvFieldConfig();
        
        // Mock CSV-Reader Setup
        $header = ['Vorgangsschlüssel', 'Autor', 'Zusammenfassung'];
        $columnIndices = ['Vorgangsschlüssel' => 0, 'Autor' => 1, 'Zusammenfassung' => 2];
        
        $this->csvFileReader->method('openCsvFile')->willReturn('mock_handle');
        $this->csvFileReader->method('readHeader')->willReturn($header);
        $this->csvFileReader->method('validateRequiredColumns')->willReturn($columnIndices);
        
        // Simuliere eine Zeile mit gültigen Daten
        $rows = [
            ['TICKET-123', 'valid.username', 'Valid Ticket Name']
        ];
        
        $this->csvFileReader->method('processRows')
            ->willReturnCallback(function ($handle, $callback) use ($rows) {
                foreach ($rows as $index => $row) {
                    $callback($row, $index + 2);
                }
            });
        $this->csvFileReader->method('closeHandle');
        
        // UserRepository sollte den Username erhalten
        $this->userRepository->expects($this->once())
            ->method('identifyUnknownUsers')
            ->with(['valid.username' => true])
            ->willReturn(['valid.username']); // Username ist unbekannt
        
        $result = $this->csvProcessor->process($file, $csvFieldConfig);
        
        // Assertions
        $this->assertCount(1, $result->validTickets, 'Ein gültiges Ticket erwartet');
        $this->assertEmpty($result->invalidRows, 'Keine ungültigen Zeilen erwartet');
        $this->assertCount(1, $result->unknownUsers, 'Ein unbekannter User erwartet');
        
        // The unknown user should be an UnknownUserWithTicket object
        $unknownUser = $result->unknownUsers[0];
        $this->assertInstanceOf(UnknownUserWithTicket::class, $unknownUser);
        $this->assertEquals('valid.username', $unknownUser->getUsernameString());
    }
}
