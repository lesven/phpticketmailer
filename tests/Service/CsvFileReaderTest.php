<?php
namespace App\Tests\Service;

use App\Service\CsvFileReader;
use PHPUnit\Framework\TestCase;

class CsvFileReaderTest extends TestCase
{
    public function testOpenReadHeaderValidateProcessClose(): void
    {
        $content = "ticketId,username,ticketName\n1,user1,Name1\n2,user2,Name2\n";
        $tmp = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tmp, $content);

        $reader = new CsvFileReader(',', 1000);

        $handle = $reader->openCsvFile($tmp);
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

        @unlink($tmp);
    }
}
