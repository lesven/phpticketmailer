<?php

namespace App\Tests\Service;

use App\Service\CsvProcessor;
use App\Service\CsvFileReader;
use App\Dto\CsvProcessingResult;
use App\Repository\UserRepository;
use App\Entity\CsvFieldConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CsvProcessorCreatedFieldTest extends TestCase
{
    public function testProcessWithCreatedFieldInCsv(): void
    {
        // CSV with created field
        $content = "Vorgangsschlüssel,Autor,Zusammenfassung,Erstellt\n";
        $content .= "T-001,user1,Ticket 1,2024-01-15\n";
        $content .= "T-002,user2,Ticket 2,2024-02-20\n";
        
        $tmp = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tmp, $content);
        $uploaded = new UploadedFile($tmp, 'test.csv', null, null, true);

        $reader = new CsvFileReader(',', 1000);
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('identifyUnknownUsers')->willReturn([]);

        $cfg = new CsvFieldConfig();
        // Use default field mapping which includes 'created' => 'Erstellt'

        $processor = new CsvProcessor($reader, $userRepository);
        $res = $processor->process($uploaded, $cfg);

        $this->assertCount(2, $res->validTickets);
        
        // Check that created field was extracted
        $ticket1 = $res->validTickets[0];
        $this->assertEquals('2024-01-15', $ticket1->created);
        
        $ticket2 = $res->validTickets[1];
        $this->assertEquals('2024-02-20', $ticket2->created);

        @unlink($tmp);
    }

    public function testProcessWithoutCreatedFieldInCsv(): void
    {
        // CSV without created field - backwards compatibility
        $content = "Vorgangsschlüssel,Autor,Zusammenfassung\n";
        $content .= "T-001,user1,Ticket 1\n";
        $content .= "T-002,user2,Ticket 2\n";
        
        $tmp = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tmp, $content);
        $uploaded = new UploadedFile($tmp, 'test.csv', null, null, true);

        $reader = new CsvFileReader(',', 1000);
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('identifyUnknownUsers')->willReturn([]);

        $cfg = new CsvFieldConfig();

        $processor = new CsvProcessor($reader, $userRepository);
        $res = $processor->process($uploaded, $cfg);

        // Should still process successfully without created field
        $this->assertCount(2, $res->validTickets);
        
        // Check that created field is null when not in CSV
        $ticket1 = $res->validTickets[0];
        $this->assertNull($ticket1->created);
        
        $ticket2 = $res->validTickets[1];
        $this->assertNull($ticket2->created);

        @unlink($tmp);
    }

    public function testProcessWithEmptyCreatedValues(): void
    {
        // CSV with created column but some empty values
        $content = "Vorgangsschlüssel,Autor,Zusammenfassung,Erstellt\n";
        $content .= "T-001,user1,Ticket 1,2024-01-15\n";
        $content .= "T-002,user2,Ticket 2,\n";  // Empty created
        $content .= "T-003,user3,Ticket 3,   \n"; // Whitespace only
        
        $tmp = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tmp, $content);
        $uploaded = new UploadedFile($tmp, 'test.csv', null, null, true);

        $reader = new CsvFileReader(',', 1000);
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('identifyUnknownUsers')->willReturn([]);

        $cfg = new CsvFieldConfig();

        $processor = new CsvProcessor($reader, $userRepository);
        $res = $processor->process($uploaded, $cfg);

        $this->assertCount(3, $res->validTickets);
        
        // First ticket should have created
        $this->assertEquals('2024-01-15', $res->validTickets[0]->created);
        
        // Second and third tickets should have null (empty values)
        $this->assertNull($res->validTickets[1]->created);
        $this->assertNull($res->validTickets[2]->created);

        @unlink($tmp);
    }
}
