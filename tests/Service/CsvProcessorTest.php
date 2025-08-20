<?php
namespace App\Tests\Service;

use App\Service\CsvProcessor;
use App\Service\CsvFileReader;
use App\Service\UserValidator;
use App\Entity\CsvFieldConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CsvProcessorTest extends TestCase
{
    public function testProcessReturnsValidAndInvalidRowsAndUnknownUsers(): void
    {
        $content = "ticketId,username,ticketName\n1,user1,Name1\n,missing,Name2\n3,user3,Name3\n";
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

        $userValidator = $this->createMock(UserValidator::class);
        $userValidator->method('identifyUnknownUsers')->willReturn(['user3']);

    $requestStack = $this->createMock(RequestStack::class);
    $store = [];
    $sessionMock = $this->createMock(SessionInterface::class);
    $sessionMock->method('set')->willReturnCallback(function($k, $v) use (&$store) { $store[$k] = $v; });
    $sessionMock->method('get')->willReturnCallback(function($k) use (&$store) { return $store[$k] ?? null; });
    $requestStack->method('getSession')->willReturn($sessionMock);

        $cfg = $this->createMock(CsvFieldConfig::class);
        $cfg->method('getFieldMapping')->willReturn(['ticketId'=>'ticketId','username'=>'username','ticketName'=>'ticketName']);

        $processor = new CsvProcessor($reader, $userValidator, $requestStack);
        $res = $processor->process($uploaded, $cfg);

        $this->assertArrayHasKey('validTickets', $res);
        $this->assertArrayHasKey('invalidRows', $res);
        $this->assertArrayHasKey('unknownUsers', $res);

        $this->assertCount(2, $res['validTickets']);
        $this->assertCount(1, $res['invalidRows']);
        $this->assertEquals(['user3'], $res['unknownUsers']);

        @unlink($tmp);
    }
}
