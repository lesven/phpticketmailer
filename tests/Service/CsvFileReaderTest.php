<?php
namespace App\Tests\Service;

use App\Service\CsvFileReader;
use PHPUnit\Framework\TestCase;

class CsvFileReaderTest extends TestCase
{
    private string $tmpFile;

    protected function tearDown(): void
    {
        if (isset($this->tmpFile) && file_exists($this->tmpFile)) {
            @unlink($this->tmpFile);
        }
    }

    public function testReadHeaderValidateAndProcessRows(): void
    {
        $content = "name,email\nAlice,alice@example.com\nBob,bob@example.com\n";
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($this->tmpFile, $content);

        $reader = new CsvFileReader(',', 1000);
        $handle = $reader->openCsvFile($this->tmpFile);

        $header = $reader->readHeader($handle);
        $this->assertSame(['name', 'email'], $header);

        $indices = $reader->validateRequiredColumns($header, ['name', 'email']);
        $this->assertSame(['name' => 0, 'email' => 1], $indices);

        $processed = [];
        $reader->processRows($handle, function ($row, $rowNumber) use (&$processed) {
            $processed[] = ['row' => $row, 'rowNumber' => $rowNumber];
        });

        $this->assertCount(2, $processed);
        $this->assertSame(['Alice', 'alice@example.com'], $processed[0]['row']);
        $this->assertSame(2, $processed[0]['rowNumber']);
        $this->assertSame(['Bob', 'bob@example.com'], $processed[1]['row']);
        $this->assertSame(3, $processed[1]['rowNumber']);

        $reader->closeHandle($handle);
        $this->assertTrue(true);
    }

    public function testValidateRequiredColumnsThrows(): void
    {
        $reader = new CsvFileReader();
        $header = ['name', 'email'];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('age');

        $reader->validateRequiredColumns($header, ['name', 'age']);
    }

    public function testOpenCsvFileThrowsOnMissing(): void
    {
        $reader = new CsvFileReader();

        $this->expectException(\Exception::class);
        $reader->openCsvFile('/path/does/not/exist.csv');
    }

    public function testReadHeaderThrowsOnEmptyFile(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'csv');
        // create empty file (no header)
        file_put_contents($this->tmpFile, "");

        $reader = new CsvFileReader(',', 1000);
        $handle = $reader->openCsvFile($this->tmpFile);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('CSV-Header');
        $reader->readHeader($handle);
    }

    public function testOpenCsvFileResolvesUploadedFile(): void
    {
        $content = "h1,h2\n1,2\n";
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($this->tmpFile, $content);

        $uploaded = $this->getMockBuilder('\Symfony\Component\HttpFoundation\File\UploadedFile')
            ->disableOriginalConstructor()
            ->getMock();
        $uploaded->method('getPathname')->willReturn($this->tmpFile);

        $reader = new CsvFileReader(',', 1000);
        $handle = $reader->openCsvFile($uploaded);

        $header = $reader->readHeader($handle);
        $this->assertSame(['h1', 'h2'], $header);
        $reader->closeHandle($handle);
    }
}
