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

class CsvProcessorTest extends TestCase
{
    public function testProcessReturnsValidAndInvalidRowsAndUnknownUsers(): void
    {
        $content = "ticketId,username,ticketName\nT-001,user1,Name1\n,missing,Name2\nT-003,user3,Name3\n";
        $tmp = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tmp, $content);

        $uploaded = new UploadedFile($tmp, 'test.csv', null, null, true);

    // Test stub for CsvFileReader behaviour (ensures correct processRows callback usage)
    $tmpPath = $tmp;
    $reader = new class($tmpPath) extends CsvFileReader {
        private string $path;
        public function __construct(string $path) { parent::__construct(',', 1000); $this->path = $path; }
        public function openCsvFile($file) {
            $path = $file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile ? $file->getPathname() : $file;
            return fopen($path, 'r');
        }
    public function readHeader($handle): array { return fgetcsv($handle, 1000, ',', '"', '\\'); }
        public function validateRequiredColumns(array $header, array $requiredColumns): array {
            $indices = [];
            foreach ($requiredColumns as $col) {
                $i = array_search($col, $header);
                if ($i === false) throw new \Exception('missing');
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
        public function closeHandle($handle): void { if (is_resource($handle)) { fclose($handle); } }
    };

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('identifyUnknownUsers')->willReturn(['user3']);

    $requestStack = $this->createMock(RequestStack::class);
    $store = [];
    $sessionMock = $this->createMock(SessionInterface::class);
    $sessionMock->method('set')->willReturnCallback(function($k, $v) use (&$store) { $store[$k] = $v; });
    $sessionMock->method('get')->willReturnCallback(function($k) use (&$store) { return $store[$k] ?? null; });
    $requestStack->method('getSession')->willReturn($sessionMock);

        $cfg = $this->createMock(CsvFieldConfig::class);
        $cfg->method('getFieldMapping')->willReturn(['ticketId'=>'ticketId','username'=>'username','ticketName'=>'ticketName']);

        $processor = new CsvProcessor($reader, $userRepository, $requestStack);
        $res = $processor->process($uploaded, $cfg);

        $this->assertArrayHasKey('validTickets', $res);
        $this->assertArrayHasKey('invalidRows', $res);
        $this->assertArrayHasKey('unknownUsers', $res);

        $this->assertCount(2, $res['validTickets']);
        $this->assertCount(1, $res['invalidRows']);
        $this->assertCount(1, $res['unknownUsers']);
        
        // The unknown user should now be an UnknownUserWithTicket object
        $unknownUser = $res['unknownUsers'][0];
        $this->assertInstanceOf(UnknownUserWithTicket::class, $unknownUser);
        $this->assertEquals('user3', $unknownUser->getUsernameString());

        @unlink($tmp);
    }

    public function testProcessWithEmptyFile(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tmp, "");
        $uploaded = new UploadedFile($tmp, 'empty.csv', null, null, true);

        $reader = $this->createMock(CsvFileReader::class);
        $reader->method('openCsvFile')->willThrowException(new \Exception('CSV-Datei konnte nicht geöffnet werden'));

        $userRepository = $this->createMock(UserRepository::class);
        $requestStack = $this->createMock(RequestStack::class);
        $cfg = $this->createMock(CsvFieldConfig::class);

        $processor = new CsvProcessor($reader, $userRepository, $requestStack);
        $this->expectException(\Exception::class);
        $processor->process($uploaded, $cfg);
        @unlink($tmp);
    }

    public function testProcessWithHeaderOnly(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tmp, "ticketId,username,ticketName\n");
        $uploaded = new UploadedFile($tmp, 'header.csv', null, null, true);

        $reader = new class($tmp) extends CsvFileReader {
            public function openCsvFile($file) { return fopen($file, 'r'); }
            public function readHeader($handle): array { return fgetcsv($handle, 1000, ',', '"', '\\'); }
            public function validateRequiredColumns(array $header, array $requiredColumns): array {
                $indices = [];
                foreach ($requiredColumns as $col) {
                    $i = array_search($col, $header);
                    if ($i === false) throw new \Exception('missing');
                    $indices[$col] = $i;
                }
                return $indices;
            }
            public function processRows($handle, callable $rowProcessor): void {
                // keine Datenzeilen
            }
            public function closeHandle($handle): void { if (is_resource($handle)) { fclose($handle); } }
        };
        $userRepository = $this->createMock(UserRepository::class);
        $requestStack = $this->createMock(RequestStack::class);
        $cfg = $this->createMock(CsvFieldConfig::class);
        $processor = new CsvProcessor($reader, $userRepository, $requestStack);
        $res = $processor->process($uploaded, $cfg);
        $this->assertCount(0, $res['validTickets']);
        $this->assertCount(0, $res['invalidRows']);
        @unlink($tmp);
    }

    public function testProcessWithDuplicateTicketIds(): void
    {
        $content = "ticketId,username,ticketName\nT-001,user1,Name1\nT-001,user2,Name2\nT-002,user3,Name3\n";
        $tmp = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tmp, $content);
        $uploaded = new UploadedFile($tmp, 'dupes.csv', null, null, true);

        $reader = new class($tmp) extends CsvFileReader {
            public function openCsvFile($file) { return fopen($file, 'r'); }
            public function readHeader($handle): array { return fgetcsv($handle, 1000, ',', '"', '\\'); }
            public function validateRequiredColumns(array $header, array $requiredColumns): array {
                $indices = [];
                foreach ($requiredColumns as $col) {
                    $i = array_search($col, $header);
                    if ($i === false) throw new \Exception('missing');
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
            public function closeHandle($handle): void { if (is_resource($handle)) { fclose($handle); } }
        };
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('identifyUnknownUsers')->willReturn([]);
        $requestStack = $this->createMock(RequestStack::class);
        $cfg = $this->createMock(CsvFieldConfig::class);
        $cfg->method('getFieldMapping')->willReturn(['ticketId'=>'ticketId','username'=>'username','ticketName'=>'ticketName']);
        $processor = new CsvProcessor($reader, $userRepository, $requestStack);
        $res = $processor->process($uploaded, $cfg);
        $ticketIds = array_map(fn(TicketData $t) => (string) $t->ticketId, $res['validTickets']);
        $this->assertContainsEquals('T-001', $ticketIds);
        $this->assertContainsEquals('T-002', $ticketIds);
        @unlink($tmp);
    }
}
