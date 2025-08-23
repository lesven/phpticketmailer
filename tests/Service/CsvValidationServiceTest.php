<?php

namespace App\Tests\Service;

use App\Service\CsvValidationService;
use App\Exception\CsvProcessingException;
use PHPUnit\Framework\TestCase;

class CsvValidationServiceTest extends TestCase
{
    private CsvValidationService $service;

    protected function setUp(): void
    {
        $this->service = new CsvValidationService();
    }

    // ========== DATEI-VALIDIERUNG (SICHERHEITSKRITISCH) ==========

    public function testValidateUploadedFileAcceptsValidCsvFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'csv');
        $csvFile = $tmpFile . '.csv';
        file_put_contents($csvFile, "header1,header2\nvalue1,value2\n");
        rename($tmpFile, $csvFile);

        $this->service->validateUploadedFile(new \SplFileInfo($csvFile));
        
        $this->assertTrue(true); // No exception thrown
        @unlink($csvFile);
    }

    public function testValidateUploadedFileAcceptsValidTxtFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'csv');
        $txtFile = $tmpFile . '.txt';
        file_put_contents($txtFile, "header1,header2\nvalue1,value2\n");
        rename($tmpFile, $txtFile);

        $this->service->validateUploadedFile(new \SplFileInfo($txtFile));
        
        $this->assertTrue(true); // No exception thrown
        @unlink($txtFile);
    }

    public function testValidateUploadedFileRejectsMaliciousExtensions(): void
    {
        $maliciousExtensions = ['.exe', '.php', '.js', '.html', '.sh', '.bat', '.cmd', '.scr', '.vbs'];
        
        foreach ($maliciousExtensions as $ext) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'csv');
            $maliciousFile = $tmpFile . $ext;
            file_put_contents($maliciousFile, "malicious content");
            rename($tmpFile, $maliciousFile);
            
            try {
                $this->service->validateUploadedFile(new \SplFileInfo($maliciousFile));
                $this->fail("File with extension {$ext} should be rejected");
            } catch (CsvProcessingException $e) {
                $this->assertStringContainsString('Nur .csv und .txt Dateien sind erlaubt', $e->getMessage());
                @unlink($maliciousFile);
                continue;
            }
            @unlink($maliciousFile);
        }
        
        $this->assertTrue(true); // All malicious extensions were properly rejected
    }

    public function testValidateUploadedFileRejectsOversizedFiles(): void
    {
        // Mock a large file by creating a SplFileInfo subclass
        $largeFileMock = $this->getMockBuilder(\SplFileInfo::class)
            ->setConstructorArgs([__FILE__])
            ->onlyMethods(['getSize', 'getExtension'])
            ->getMock();
        
        $largeFileMock->method('getSize')->willReturn(11 * 1024 * 1024); // 11 MB
        $largeFileMock->method('getExtension')->willReturn('csv');

        $this->expectException(CsvProcessingException::class);
        $this->expectExceptionMessage('Die Datei ist zu groß');
        
        $this->service->validateUploadedFile($largeFileMock);
    }

    public function testValidateUploadedFileWithMimeTypeValidation(): void
    {
        // Create a mock that properly extends SplFileInfo with getMimeType capability
        $tmpFile = tempnam(sys_get_temp_dir(), 'csv');
        $csvFile = $tmpFile . '.csv';
        file_put_contents($csvFile, "test,data\n1,2\n");
        rename($tmpFile, $csvFile);

        // Create a mock that extends our real file with getMimeType method
        $fileMock = $this->getMockBuilder(\SplFileInfo::class)
            ->setConstructorArgs([$csvFile])
            ->addMethods(['getMimeType']) // Use addMethods for non-existent methods
            ->getMock();
        
        $fileMock->method('getMimeType')->willReturn('application/x-executable');

        $this->expectException(CsvProcessingException::class);
        $this->expectExceptionMessage('Ungültiger Dateityp');
        
        try {
            $this->service->validateUploadedFile($fileMock);
        } finally {
            @unlink($csvFile);
        }
    }

    public function testValidateUploadedFileWithValidMimeType(): void
    {
        // Test that valid MIME types are accepted
        $tmpFile = tempnam(sys_get_temp_dir(), 'csv');
        $csvFile = $tmpFile . '.csv';
        file_put_contents($csvFile, "test,data\n1,2\n");
        rename($tmpFile, $csvFile);

        $validMimeTypes = [
            'text/csv',
            'text/plain',
            'application/csv',
            'application/vnd.ms-excel'
        ];

        foreach ($validMimeTypes as $mimeType) {
            $fileMock = $this->getMockBuilder(\SplFileInfo::class)
                ->setConstructorArgs([$csvFile])
                ->addMethods(['getMimeType'])
                ->getMock();
            
            $fileMock->method('getMimeType')->willReturn($mimeType);

            // Should not throw exception
            $this->service->validateUploadedFile($fileMock);
        }

        @unlink($csvFile);
        $this->assertTrue(true); // All valid MIME types accepted
    }

    // ========== CSV-STRUKTUR-VALIDIERUNG ==========

    public function testValidateCsvStructureWithValidHeaders(): void
    {
        $headers = ['username', 'email', 'ticketId', 'ticketName'];
        $required = ['username', 'email', 'ticketId'];

        $this->service->validateCsvStructure($headers, $required);
        
        $this->assertTrue(true); // No exception thrown
    }

    public function testValidateCsvStructureWithMissingColumns(): void
    {
        $headers = ['username', 'email'];
        $required = ['username', 'email', 'ticketId', 'ticketName'];

        $this->expectException(CsvProcessingException::class);
        $this->expectExceptionMessage('Folgende erforderliche Spalten fehlen');
        
        try {
            $this->service->validateCsvStructure($headers, $required);
        } catch (CsvProcessingException $e) {
            $this->assertStringContainsString('ticketId', $e->getMessage());
            $this->assertStringContainsString('ticketName', $e->getMessage());
            $context = $e->getContext();
            $this->assertEquals(['ticketId', 'ticketName'], $context['missing_columns']);
            throw $e;
        }
    }

    public function testValidateCsvStructureWithEmptyHeaders(): void
    {
        $headers = [];
        $required = ['username', 'email'];

        $this->expectException(CsvProcessingException::class);
        $this->service->validateCsvStructure($headers, $required);
    }

    // ========== ZEILEN-VALIDIERUNG ==========

    public function testValidateCsvRowWithValidData(): void
    {
        $row = [
            'username' => 'john_doe',
            'email' => 'john.doe@example.com',
            'ticketId' => 'TICKET-123',
            'ticketName' => 'Test Ticket'
        ];
        $required = ['username', 'email', 'ticketId'];

        $result = $this->service->validateCsvRow($row, $required, 5);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals(5, $result['line_number']);
    }

    public function testValidateCsvRowWithMissingRequiredFields(): void
    {
        $row = [
            'username' => 'john_doe',
            'email' => 'john.doe@example.com'
            // ticketId fehlt
        ];
        $required = ['username', 'email', 'ticketId'];

        $result = $this->service->validateCsvRow($row, $required, 10);

        $this->assertFalse($result['valid']);
        $this->assertContains("Feld 'ticketId' ist leer oder fehlt", $result['errors']);
        $this->assertEquals(10, $result['line_number']);
    }

    public function testValidateCsvRowWithEmptyRequiredFields(): void
    {
        $row = [
            'username' => '',
            'email' => '   ',
            'ticketId' => 'TICKET-123'
        ];
        $required = ['username', 'email', 'ticketId'];

        $result = $this->service->validateCsvRow($row, $required, 15);

        $this->assertFalse($result['valid']);
        $this->assertContains("Feld 'username' ist leer oder fehlt", $result['errors']);
        $this->assertContains("Feld 'email' ist leer oder fehlt", $result['errors']);
    }

    public function testValidateCsvRowWithInvalidEmail(): void
    {
        $row = [
            'username' => 'john_doe',
            'email' => 'invalid-email-format',
            'ticketId' => 'TICKET-123'
        ];
        $required = ['username', 'email', 'ticketId'];

        $result = $this->service->validateCsvRow($row, $required, 20);

        $this->assertFalse($result['valid']);
        $this->assertContains('Ungültige E-Mail-Adresse: invalid-email-format', $result['errors']);
    }

    public function testValidateCsvRowWithInvalidTicketId(): void
    {
        $row = [
            'username' => 'john_doe',
            'email' => 'john@example.com',
            'ticketId' => 'INVALID!@#$%'
        ];
        $required = ['username', 'email', 'ticketId'];

        $result = $this->service->validateCsvRow($row, $required, 25);

        $this->assertFalse($result['valid']);
        $this->assertContains('Ungültige Ticket-ID: INVALID!@#$%', $result['errors']);
    }

    // ========== E-MAIL-VALIDIERUNG (SICHERHEITSKRITISCH) ==========

    public function testIsValidEmailWithValidEmails(): void
    {
        $validEmails = [
            'user@example.com',
            'user.name@example.com',
            'user+tag@example.com',
            'user123@example-domain.com',
            'user@sub.example.com',
            'a@b.co'
        ];

        foreach ($validEmails as $email) {
            $this->assertTrue($this->service->isValidEmail($email), "Email '{$email}' should be valid");
        }
    }

    public function testIsValidEmailWithInvalidEmails(): void
    {
        $invalidEmails = [
            '',
            'plainaddress',
            '@example.com',
            'user@',
            'user@.com',
            'user@domain..com',
            'user@domain@domain.com',
            'user name@example.com', // Space in local part
            'user@ex ample.com', // Space in domain
            'user@domain.',
            str_repeat('a', 255) . '@example.com' // Too long (>254 chars)
        ];

        foreach ($invalidEmails as $email) {
            $this->assertFalse($this->service->isValidEmail($email), "Email '{$email}' should be invalid");
        }
    }

    public function testIsValidEmailWithLengthLimits(): void
    {
        // Test a reasonably long but valid email
        $longButValidEmail = str_repeat('a', 50) . '@example.com'; // 50 + 12 = 62 chars
        $this->assertTrue($this->service->isValidEmail($longButValidEmail));

        // Test over 254 character limit (should be invalid)
        $tooLongEmail = str_repeat('a', 250) . '@example.com'; // 250 + 12 = 262 chars
        $this->assertFalse($this->service->isValidEmail($tooLongEmail));
    }

    // ========== TICKET-ID-VALIDIERUNG ==========

    public function testIsValidTicketIdWithValidIds(): void
    {
        $validIds = [
            'TICKET-123',
            'T-001',
            'ABC123',
            '123456',
            'TICKET_001',
            'Project-Feature-123',
            'a1b2c3',
            str_repeat('A', 50) // Exactly 50 chars (limit)
        ];

        foreach ($validIds as $ticketId) {
            $this->assertTrue($this->service->isValidTicketId($ticketId), "TicketId '{$ticketId}' should be valid");
        }
    }

    public function testIsValidTicketIdWithInvalidIds(): void
    {
        $invalidIds = [
            '',
            '   ', // Only whitespace
            'TICKET!@#$%', // Special characters
            'TICKET äöü', // Unicode/umlauts
            'TICKET 123', // Space
            'TICKET.123', // Dot not allowed
            'TICKET/123', // Slash not allowed
            str_repeat('A', 51) // Too long (>50 chars)
        ];

        foreach ($invalidIds as $ticketId) {
            $this->assertFalse($this->service->isValidTicketId($ticketId), "TicketId '{$ticketId}' should be invalid");
        }
    }

    // ========== DATEN-BEREINIGUNG ==========

    public function testSanitizeCsvDataRemovesWhitespaceAndDecodesEntities(): void
    {
        $data = [
            'username' => '  john_doe  ',
            'email' => "  john@example.com  ",
            'description' => '&lt;Test&gt; &amp; &quot;Quote&quot;',
            'number' => 123
        ];

        $result = $this->service->sanitizeCsvData($data);

        $this->assertEquals('john_doe', $result['username']);
        $this->assertEquals('john@example.com', $result['email']);
        $this->assertEquals('<Test> & "Quote"', $result['description']);
        $this->assertEquals(123, $result['number']); // Numbers should remain unchanged
    }

    public function testSanitizeCsvDataWithEmptyAndNullValues(): void
    {
        $data = [
            'empty_string' => '',
            'whitespace_only' => '   ',
            'null_value' => null,
            'zero' => 0
        ];

        $result = $this->service->sanitizeCsvData($data);

        $this->assertEquals('', $result['empty_string']);
        $this->assertEquals('', $result['whitespace_only']);
        $this->assertNull($result['null_value']);
        $this->assertEquals(0, $result['zero']);
    }

    // ========== DUPLIKATE-ENTFERNUNG ==========

    public function testRemoveDuplicatesWithUniqueRecords(): void
    {
        $records = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
            ['id' => 3, 'name' => 'Bob']
        ];

        $result = $this->service->removeDuplicates($records, 'id');

        $this->assertCount(3, $result);
        $this->assertEquals($records, $result);
    }

    public function testRemoveDuplicatesWithDuplicateRecords(): void
    {
        $records = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
            ['id' => 1, 'name' => 'John Duplicate'], // Duplicate ID
            ['id' => 3, 'name' => 'Bob'],
            ['id' => 2, 'name' => 'Jane Again'] // Another duplicate
        ];

        $result = $this->service->removeDuplicates($records, 'id');

        $this->assertCount(3, $result);
        $this->assertEquals(1, $result[0]['id']);
        $this->assertEquals('John', $result[0]['name']); // First occurrence kept
        $this->assertEquals(2, $result[1]['id']);
        $this->assertEquals('Jane', $result[1]['name']); // First occurrence kept
        $this->assertEquals(3, $result[2]['id']);
    }

    public function testRemoveDuplicatesWithMissingKeyField(): void
    {
        $records = [
            ['id' => 1, 'name' => 'John'],
            ['name' => 'Jane'], // Missing 'id' field
            ['id' => 2, 'name' => 'Bob']
        ];

        $result = $this->service->removeDuplicates($records, 'id');

        $this->assertCount(2, $result); // Record without 'id' should be skipped
        $this->assertEquals(1, $result[0]['id']);
        $this->assertEquals(2, $result[1]['id']);
    }

    public function testRemoveDuplicatesWithEmptyArray(): void
    {
        $result = $this->service->removeDuplicates([], 'id');
        $this->assertEmpty($result);
    }

    // ========== INTEGRATION TESTS ==========

    public function testCompleteValidationWorkflow(): void
    {
        // Test complete validation workflow with valid data
        $headers = ['username', 'email', 'ticketId'];
        $required = ['username', 'email', 'ticketId'];
        
        // 1. Structure validation
        $this->service->validateCsvStructure($headers, $required);
        
        // 2. Row validation
        $validRow = [
            'username' => 'john_doe',
            'email' => 'john@example.com',
            'ticketId' => 'TICKET-123'
        ];
        $result = $this->service->validateCsvRow($validRow, $required, 1);
        $this->assertTrue($result['valid']);
        
        // 3. Data sanitization
        $dirtyData = ['username' => '  john_doe  ', 'extra' => '&lt;test&gt;'];
        $cleaned = $this->service->sanitizeCsvData($dirtyData);
        $this->assertEquals('john_doe', $cleaned['username']);
        
        // 4. Duplicate removal
        $records = [
            ['username' => 'john', 'id' => 1],
            ['username' => 'jane', 'id' => 2],
            ['username' => 'john', 'id' => 1] // Duplicate
        ];
        $unique = $this->service->removeDuplicates($records, 'username');
        $this->assertCount(2, $unique);
    }

    public function testConstructorCreatesService(): void
    {
        $service = new CsvValidationService();
        $this->assertInstanceOf(CsvValidationService::class, $service);
    }
}
