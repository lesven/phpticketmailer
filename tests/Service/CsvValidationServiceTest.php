<?php
namespace App\Tests\Service;

use App\Service\CsvValidationService;
use App\Exception\CsvProcessingException;
use PHPUnit\Framework\TestCase;

class CsvValidationServiceTest extends TestCase
{
    private CsvValidationService $svc;

    protected function setUp(): void
    {
        $this->svc = new CsvValidationService();
    }

    public function testIsValidEmailAndTicketId(): void
    {
        $this->assertTrue($this->svc->isValidEmail('foo@example.com'));
        $this->assertFalse($this->svc->isValidEmail('invalid-email'));

        $this->assertTrue($this->svc->isValidTicketId('ABC-123_foo'));
        $this->assertFalse($this->svc->isValidTicketId('!invalid#'));
    }

    public function testValidateCsvRow(): void
    {
        $row = ['email' => 'a@b.com', 'ticketId' => 'T1', 'username' => 'u1'];
        $res = $this->svc->validateCsvRow($row, ['email','ticketId','username'], 5);
        $this->assertTrue($res['valid']);

        $row2 = ['email' => 'bad', 'ticketId' => '', 'username' => ''];
        $res2 = $this->svc->validateCsvRow($row2, ['email','ticketId','username'], 6);
        $this->assertFalse($res2['valid']);
        $this->assertNotEmpty($res2['errors']);
    }

    public function testValidateUploadedFileRejectsByExtension(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'csv');
        // create a fake .exe extension
        $bad = $tmp . '.exe';
        copy($tmp, $bad);

        $this->expectException(CsvProcessingException::class);
        $this->svc->validateUploadedFile(new \SplFileInfo($bad));

        @unlink($bad);
        @unlink($tmp);
    }
}
