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

class CsvProcessorTest extends TestCase
{
    public function testProcessReturnsValidAndInvalidRowsAndUnknownUsers(): void
    {
        $content = "ticketId,username,ticketName\nT-001,user1,Name1\n,missing,Name2\nT-003,user3,Name3\n";
        $tmp = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tmp, $content);

        $uploaded = new UploadedFile($tmp, 'test.csv', null, null, true);

        $reader = $this->createCsvFileReaderFromPath($tmp);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('identifyUnknownUsers')->willReturn(['user3']);

        $cfg = $this->createMock(CsvFieldConfig::class);
        $cfg->method('getFieldMapping')->willReturn(['ticketId'=>'ticketId','username'=>'username','ticketName'=>'ticketName']);

        $processor = new CsvProcessor($reader, $userRepository);
        $res = $processor->process($uploaded, $cfg);

        $this->assertInstanceOf(CsvProcessingResult::class, $res);

        $this->assertCount(2, $res->validTickets);
        $this->assertCount(1, $res->invalidRows);
        $this->assertCount(1, $res->unknownUsers);
        
        // The unknown user should be an UnknownUserWithTicket object
        $unknownUser = $res->unknownUsers[0];
        $this->assertInstanceOf(UnknownUserWithTicket::class, $unknownUser);
        $this->assertEquals('user3', $unknownUser->getUsernameString());

        @unlink($tmp);
    }

    public function testProcessWithEmptyFile(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tmp, "");
        $uploaded = new UploadedFile($tmp, 'empty.csv', null, null, true);

        $reader = $this->createMock(CsvFileReaderInterface::class);
        $reader->method('openCsvFile')->willThrowException(new \Exception('CSV-Datei konnte nicht geöffnet werden'));

        $userRepository = $this->createMock(UserRepository::class);
        $cfg = $this->createMock(CsvFieldConfig::class);

        $processor = new CsvProcessor($reader, $userRepository);
        $this->expectException(\Exception::class);
        $processor->process($uploaded, $cfg);
        @unlink($tmp);
    }

    public function testProcessWithHeaderOnly(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tmp, "ticketId,username,ticketName\n");
        $uploaded = new UploadedFile($tmp, 'header.csv', null, null, true);

        $reader = $this->createCsvFileReaderFromPath($tmp, hasDataRows: false);

        $userRepository = $this->createMock(UserRepository::class);
        $cfg = $this->createMock(CsvFieldConfig::class);
        $cfg->method('getFieldMapping')->willReturn(['ticketId'=>'ticketId','username'=>'username','ticketName'=>'ticketName']);
        
        $processor = new CsvProcessor($reader, $userRepository);
        $res = $processor->process($uploaded, $cfg);
        $this->assertCount(0, $res->validTickets);
        $this->assertCount(0, $res->invalidRows);
        @unlink($tmp);
    }

    public function testProcessWithDuplicateTicketIds(): void
    {
        $content = "ticketId,username,ticketName\nT-001,user1,Name1\nT-001,user2,Name2\nT-002,user3,Name3\n";
        $tmp = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tmp, $content);
        $uploaded = new UploadedFile($tmp, 'dupes.csv', null, null, true);

        $reader = $this->createCsvFileReaderFromPath($tmp);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('identifyUnknownUsers')->willReturn([]);
        $cfg = $this->createMock(CsvFieldConfig::class);
        $cfg->method('getFieldMapping')->willReturn(['ticketId'=>'ticketId','username'=>'username','ticketName'=>'ticketName']);
        
        $processor = new CsvProcessor($reader, $userRepository);
        $res = $processor->process($uploaded, $cfg);
        $ticketIds = array_map(fn(TicketData $t) => (string) $t->ticketId, $res->validTickets);
        $this->assertContainsEquals('T-001', $ticketIds);
        $this->assertContainsEquals('T-002', $ticketIds);
        @unlink($tmp);
    }

    /**
     * Erstellt einen CsvFileReaderInterface-Mock basierend auf einer realen CSV-Datei
     */
    private function createCsvFileReaderFromPath(string $path, bool $hasDataRows = true): CsvFileReaderInterface
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, 1000, ',', '"', '\\');
        
        // Sammle Datenzeilen
        $rows = [];
        if ($hasDataRows) {
            while (($row = fgetcsv($handle, 1000, ',', '"', '\\')) !== false) {
                $rows[] = $row;
            }
        }
        fclose($handle);

        $reader = $this->createMock(CsvFileReaderInterface::class);
        $reader->method('openCsvFile')->willReturn('mock_handle');
        $reader->method('readHeader')->willReturn($header);
        $reader->method('validateRequiredColumns')->willReturnCallback(
            function (array $header, array $requiredColumns) {
                $indices = [];
                foreach ($requiredColumns as $col) {
                    $i = array_search($col, $header);
                    if ($i === false) throw new \Exception('missing');
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
