<?php
namespace App\Tests\Service;

use App\Service\CsvFileReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CsvFileReaderTest extends TestCase
{
    private CsvFileReader $csvFileReader;
    private string $tempFile;

    protected function setUp(): void
    {
        $this->csvFileReader = new CsvFileReader(',', 1000);
        $this->tempFile = tempnam(sys_get_temp_dir(), 'csv_test_');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testConstructorWithDefaultParameters(): void
    {
        $reader = new CsvFileReader();
        $this->assertInstanceOf(CsvFileReader::class, $reader);
    }

    public function testConstructorWithCustomParameters(): void
    {
        $reader = new CsvFileReader(';', 2000);
        $this->assertInstanceOf(CsvFileReader::class, $reader);
    }

    public function testOpenReadHeaderValidateProcessClose(): void
    {
        $content = "ticketId,username,ticketName\n1,user1,Name1\n2,user2,Name2\n";
        file_put_contents($this->tempFile, $content);

        $reader = new CsvFileReader(',', 1000);

        $handle = $reader->openCsvFile($this->tempFile);
        $this->assertIsResource($handle);

        $header = $reader->readHeader($handle);
        $this->assertEquals(['ticketId','username','ticketName'], $header);

        $indices = $reader->validateRequiredColumns($header, ['ticketId','username','ticketName']);
        $this->assertArrayHasKey('ticketId', $indices);

        $rows = [];
        $reader->processRows($handle, function($row, $number) use (&$rows) {
            $rows[] = [$number, $row];
        });

        $this->assertCount(2, $rows);

        $reader->closeHandle($handle);
        $this->assertFalse(is_resource($handle));
    }

    public function testOpenCsvFileWithUploadedFile(): void
    {
        $content = "col1,col2\nval1,val2\n";
        file_put_contents($this->tempFile, $content);

        $uploadedFile = new UploadedFile($this->tempFile, 'test.csv', 'text/csv', null, true);
        $handle = $this->csvFileReader->openCsvFile($uploadedFile);

        $this->assertIsResource($handle);
        $this->csvFileReader->closeHandle($handle);
    }

    public function testOpenCsvFileWithString(): void
    {
        $content = "col1,col2\nval1,val2\n";
        file_put_contents($this->tempFile, $content);

        $handle = $this->csvFileReader->openCsvFile($this->tempFile);

        $this->assertIsResource($handle);
        $this->csvFileReader->closeHandle($handle);
    }

    public function testOpenCsvFileThrowsExceptionForNonExistentFile(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('CSV-Datei konnte nicht geöffnet werden');

        $this->csvFileReader->openCsvFile('/non/existent/file.csv');
    }

    public function testReadHeaderSuccessfully(): void
    {
        $content = "name,email,age\nJohn,john@example.com,30\n";
        file_put_contents($this->tempFile, $content);

        $handle = $this->csvFileReader->openCsvFile($this->tempFile);
        $header = $this->csvFileReader->readHeader($handle);

        $this->assertEquals(['name', 'email', 'age'], $header);
        $this->csvFileReader->closeHandle($handle);
    }

    public function testReadHeaderWithCustomDelimiter(): void
    {
        $content = "name;email;age\nJohn;john@example.com;30\n";
        file_put_contents($this->tempFile, $content);

        $reader = new CsvFileReader(';');
        $handle = $reader->openCsvFile($this->tempFile);
        $header = $reader->readHeader($handle);

        $this->assertEquals(['name', 'email', 'age'], $header);
        $reader->closeHandle($handle);
    }

    public function testReadHeaderThrowsExceptionForEmptyFile(): void
    {
        file_put_contents($this->tempFile, '');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('CSV-Header konnte nicht gelesen werden');

        $handle = $this->csvFileReader->openCsvFile($this->tempFile);
        $this->csvFileReader->readHeader($handle);
    }

    public function testValidateRequiredColumnsSuccessfully(): void
    {
        $header = ['id', 'name', 'email', 'status'];
        $requiredColumns = ['id', 'email'];

        $indices = $this->csvFileReader->validateRequiredColumns($header, $requiredColumns);

        $this->assertEquals(['id' => 0, 'email' => 2], $indices);
    }

    public function testValidateRequiredColumnsWithAllColumns(): void
    {
        $header = ['id', 'name', 'email'];
        $requiredColumns = ['id', 'name', 'email'];

        $indices = $this->csvFileReader->validateRequiredColumns($header, $requiredColumns);

        $this->assertEquals(['id' => 0, 'name' => 1, 'email' => 2], $indices);
    }

    public function testValidateRequiredColumnsThrowsExceptionForMissingColumns(): void
    {
        $header = ['id', 'name'];
        $requiredColumns = ['id', 'email', 'status'];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('CSV-Datei enthält nicht alle erforderlichen Spalten: email, status');

        $this->csvFileReader->validateRequiredColumns($header, $requiredColumns);
    }

    public function testValidateRequiredColumnsThrowsExceptionForSingleMissingColumn(): void
    {
        $header = ['id', 'name'];
        $requiredColumns = ['id', 'email'];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('CSV-Datei enthält nicht alle erforderlichen Spalten: email');

        $this->csvFileReader->validateRequiredColumns($header, $requiredColumns);
    }

    public function testValidateRequiredColumnsWithEmptyRequiredArray(): void
    {
        $header = ['id', 'name', 'email'];
        $requiredColumns = [];

        $indices = $this->csvFileReader->validateRequiredColumns($header, $requiredColumns);

        $this->assertEquals([], $indices);
    }

    public function testProcessRowsWithMultipleRows(): void
    {
        $content = "id,name\n1,John\n2,Jane\n3,Bob\n";
        file_put_contents($this->tempFile, $content);

        $handle = $this->csvFileReader->openCsvFile($this->tempFile);
        $this->csvFileReader->readHeader($handle); // Skip header

        $processedRows = [];
        $this->csvFileReader->processRows($handle, function($row, $rowNumber) use (&$processedRows) {
            $processedRows[] = ['data' => $row, 'line' => $rowNumber];
        });

        $this->assertCount(3, $processedRows);
        $this->assertEquals(['1', 'John'], $processedRows[0]['data']);
        $this->assertEquals(2, $processedRows[0]['line']);
        $this->assertEquals(['2', 'Jane'], $processedRows[1]['data']);
        $this->assertEquals(3, $processedRows[1]['line']);
        $this->assertEquals(['3', 'Bob'], $processedRows[2]['data']);
        $this->assertEquals(4, $processedRows[2]['line']);

        $this->csvFileReader->closeHandle($handle);
    }

    public function testProcessRowsWithEmptyDataSection(): void
    {
        $content = "id,name\n";
        file_put_contents($this->tempFile, $content);

        $handle = $this->csvFileReader->openCsvFile($this->tempFile);
        $this->csvFileReader->readHeader($handle); // Skip header

        $processedRows = [];
        $this->csvFileReader->processRows($handle, function($row, $rowNumber) use (&$processedRows) {
            $processedRows[] = ['data' => $row, 'line' => $rowNumber];
        });

        $this->assertCount(0, $processedRows);
        $this->csvFileReader->closeHandle($handle);
    }

    public function testProcessRowsWithCustomDelimiter(): void
    {
        $content = "id;name\n1;John\n2;Jane\n";
        file_put_contents($this->tempFile, $content);

        $reader = new CsvFileReader(';');
        $handle = $reader->openCsvFile($this->tempFile);
        $reader->readHeader($handle); // Skip header

        $processedRows = [];
        $reader->processRows($handle, function($row) use (&$processedRows) {
            $processedRows[] = $row;
        });

        $this->assertCount(2, $processedRows);
        $this->assertEquals(['1', 'John'], $processedRows[0]);
        $this->assertEquals(['2', 'Jane'], $processedRows[1]);

        $reader->closeHandle($handle);
    }

    public function testCloseHandleWithValidResource(): void
    {
        $content = "id,name\n1,John\n";
        file_put_contents($this->tempFile, $content);

        $handle = $this->csvFileReader->openCsvFile($this->tempFile);
        $this->assertIsResource($handle);

        $this->csvFileReader->closeHandle($handle);
        $this->assertFalse(is_resource($handle));
    }

    public function testCloseHandleWithNull(): void
    {
        // Should not throw any exception
        $this->csvFileReader->closeHandle(null);
        $this->assertTrue(true); // Test passes if no exception is thrown
    }

    public function testCloseHandleWithFalse(): void
    {
        // Should not throw any exception
        $this->csvFileReader->closeHandle(false);
        $this->assertTrue(true); // Test passes if no exception is thrown
    }

    public function testCompleteWorkflowWithComplexCsv(): void
    {
        $content = "ticket_id,user_name,ticket_title,priority,status\n" .
                   "1,john.doe,Login Issue,high,open\n" .
                   "2,jane.smith,Password Reset,medium,resolved\n" .
                   "3,bob.wilson,Bug Report,low,pending\n";
        file_put_contents($this->tempFile, $content);

        $handle = $this->csvFileReader->openCsvFile($this->tempFile);
        $header = $this->csvFileReader->readHeader($handle);
        
        $requiredColumns = ['ticket_id', 'user_name', 'ticket_title'];
        $indices = $this->csvFileReader->validateRequiredColumns($header, $requiredColumns);

        $tickets = [];
        $this->csvFileReader->processRows($handle, function($row) use (&$tickets, $indices) {
            $tickets[] = [
                'id' => $row[$indices['ticket_id']],
                'user' => $row[$indices['user_name']],
                'title' => $row[$indices['ticket_title']]
            ];
        });

        $this->assertCount(3, $tickets);
        $this->assertEquals('1', $tickets[0]['id']);
        $this->assertEquals('john.doe', $tickets[0]['user']);
        $this->assertEquals('Login Issue', $tickets[0]['title']);

        $this->csvFileReader->closeHandle($handle);
    }

    public function testReadHeaderWithUnicodeCharacters(): void
    {
        $content = "nämé,émail,âge\nJöhn,jöhn@example.com,30\n";
        file_put_contents($this->tempFile, $content);

        $handle = $this->csvFileReader->openCsvFile($this->tempFile);
        $header = $this->csvFileReader->readHeader($handle);

        $this->assertEquals(['nämé', 'émail', 'âge'], $header);
        $this->csvFileReader->closeHandle($handle);
    }

    public function testReadHeaderWithDuplicateColumns(): void
    {
        $content = "id,name,id\n1,John,2\n";
        file_put_contents($this->tempFile, $content);

        $handle = $this->csvFileReader->openCsvFile($this->tempFile);
        $header = $this->csvFileReader->readHeader($handle);

        $this->assertEquals(['id', 'name', 'id'], $header);
        $this->csvFileReader->closeHandle($handle);
    }

    public function testProcessRowsWithMalformedRow(): void
    {
        $content = "id,name\n1,John\n2\n3,Jane,Extra\n";
        file_put_contents($this->tempFile, $content);

        $handle = $this->csvFileReader->openCsvFile($this->tempFile);
        $this->csvFileReader->readHeader($handle);

        $malformed = [];
        $this->csvFileReader->processRows($handle, function($row, $rowNumber) use (&$malformed) {
            if (count($row) !== 2) {
                $malformed[] = $rowNumber;
            }
        });
        $this->assertContains(3, $malformed); // Zeile mit zu wenig Spalten
        $this->assertContains(4, $malformed); // Zeile mit zu vielen Spalten
        $this->csvFileReader->closeHandle($handle);
    }

    public function testProcessRowsWithLargeFile(): void
    {
        $rows = [];
        $content = "id,name\n";
        for ($i = 1; $i <= 1000; $i++) {
            $content .= "$i,Name$i\n";
        }
        file_put_contents($this->tempFile, $content);

        $handle = $this->csvFileReader->openCsvFile($this->tempFile);
        $this->csvFileReader->readHeader($handle);
        $this->csvFileReader->processRows($handle, function($row) use (&$rows) {
            $rows[] = $row;
        });
        $this->assertCount(1000, $rows);
        $this->csvFileReader->closeHandle($handle);
    }
}
