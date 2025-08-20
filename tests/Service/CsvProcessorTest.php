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

        $reader = $this->createMock(CsvFileReader::class);
        $reader->method('openCsvFile')->willReturn(fopen($tmp, 'r'));
        $reader->method('readHeader')->willReturn(['ticketId','username','ticketName']);
        $reader->method('validateRequiredColumns')->willReturn(['ticketId'=>0,'username'=>1,'ticketName'=>2]);
        $reader->method('processRows')->willReturnCallback(function($_handle, $rowCallback) use ($tmp) {
            // ignore the provided handle and read directly from the temp file
            $h = fopen($tmp, 'r');
            fgetcsv($h); // skip header
            $n = 1;
            while (($r = fgetcsv($h)) !== false) {
                $n++;
                $rowCallback($r, $n);
            }
            fclose($h);
        });

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
