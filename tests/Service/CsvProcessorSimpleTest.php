<?php
namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;
use App\Service\CsvProcessor;
use App\Service\CsvFileReaderInterface;
use App\Dto\CsvProcessingResult;
use App\Entity\CsvFieldConfig;
use App\ValueObject\TicketData;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CsvProcessorSimpleTest extends TestCase
{
    public function testTicketNameIsTruncatedTo50Chars()
    {
        $longName = str_repeat('X', 60);
        
        $reader = $this->createMock(CsvFileReaderInterface::class);
        $reader->method('openCsvFile')->willReturn('mock_handle');
        $reader->method('readHeader')->willReturn(['ticketId', 'username', 'ticketName']);
        $reader->method('validateRequiredColumns')->willReturn([
            'ticketId' => 0, 'username' => 1, 'ticketName' => 2
        ]);
        $reader->method('processRows')->willReturnCallback(
            function ($handle, callable $rowProcessor) use ($longName) {
                $rowProcessor(['123', 'user', $longName], 2);
            }
        );
        $reader->method('closeHandle');

        $userRepository = $this->createMock(\App\Repository\UserRepository::class);
        $userRepository->method('identifyUnknownUsers')->willReturn([]);

        $cfg = $this->createMock(CsvFieldConfig::class);
        $cfg->method('getFieldMapping')->willReturn([
            'ticketId' => 'ticketId', 'username' => 'username', 'ticketName' => 'ticketName'
        ]);

        $processor = new CsvProcessor($reader, $userRepository);

        $uploaded = $this->createMock(UploadedFile::class);
        $res = $processor->process($uploaded, $cfg);

        $this->assertCount(1, $res->validTickets);
        $this->assertEquals(50, mb_strlen((string) $res->validTickets[0]->ticketName));
        $this->assertEquals(substr($longName, 0, 50), (string) $res->validTickets[0]->ticketName);
    }
}
