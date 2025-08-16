<?php
namespace App\Tests\Service;

use App\Service\CsvProcessor;
use App\Service\CsvFileReader;
use App\Service\UserValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class CsvProcessorTest extends TestCase
{
    public function testProcessHandlesValidAndInvalidRowsAndStoresInSession(): void
    {
        // Header and rows to emulate CSV content
        $header = ['ticketId', 'username', 'ticketName'];
        $rows = [
            ['1', 'alice', 'Ticket A'],
            ['', 'bob', 'Ticket B'], // invalid: missing ticketId
            ['2', 'charlie', ''] // valid, empty ticketName -> null allowed
        ];

    // Mock CsvFileReader
    $csvReader = $this->createMock(CsvFileReader::class);
    /** @var CsvFileReader $csvReader */
        $csvReader->expects($this->once())
            ->method('openCsvFile')
            ->willReturn('fake-handle');

        $csvReader->expects($this->once())
            ->method('readHeader')
            ->with('fake-handle')
            ->willReturn($header);

        $csvReader->expects($this->once())
            ->method('validateRequiredColumns')
            ->with($header, $this->anything())
            ->willReturn(['ticketId' => 0, 'username' => 1, 'ticketName' => 2]);

        // processRows will invoke the provided callback for each row
        $csvReader->expects($this->once())
            ->method('processRows')
            ->with('fake-handle', $this->isType('callable'))
            ->willReturnCallback(function ($handle, $callback) use ($rows) {
                $rowNumber = 1; // header
                foreach ($rows as $row) {
                    $rowNumber++;
                    $callback($row, $rowNumber);
                }
            });

        $csvReader->expects($this->once())
            ->method('closeHandle')
            ->with('fake-handle');

    // Mock UserValidator
    $userValidator = $this->createMock(UserValidator::class);
    /** @var UserValidator $userValidator */
        // Simulate that 'alice' is known, 'charlie' is unknown
        $userValidator->expects($this->once())
            ->method('identifyUnknownUsers')
            ->with($this->callback(function ($uniqueUsernames) {
                // ensure keys include alice and charlie
                return isset($uniqueUsernames['alice']) && isset($uniqueUsernames['charlie']);
            }))
            ->willReturn(['charlie']);

        // Prepare RequestStack + Session
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $request = new Request();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $processor = new CsvProcessor($csvReader, $userValidator, $requestStack);

        // Create a dummy UploadedFile is not necessary because CsvFileReader is mocked
    $dummyFile = $this->getMockBuilder('\Symfony\Component\HttpFoundation\File\UploadedFile')
            ->disableOriginalConstructor()
            ->getMock();
    /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $dummyFile */

        $result = $processor->process($dummyFile);

        $this->assertArrayHasKey('validTickets', $result);
        $this->assertArrayHasKey('invalidRows', $result);
        $this->assertArrayHasKey('unknownUsers', $result);

        // Expect 2 valid rows: '1','alice' and '2','charlie'
        $this->assertCount(2, $result['validTickets']);
        $this->assertSame(['ticketId' => '1', 'username' => 'alice', 'ticketName' => 'Ticket A'], $result['validTickets'][0]);
        $this->assertSame(['ticketId' => '2', 'username' => 'charlie', 'ticketName' => null], $result['validTickets'][1]);

        // Expect 1 invalid row (rowNumber 3 for the second row in $rows)
        $this->assertCount(1, $result['invalidRows']);
        $this->assertSame(3, $result['invalidRows'][0]['rowNumber']);

        // unknownUsers should match mocked return
        $this->assertSame(['charlie'], $result['unknownUsers']);

        // Session should contain the valid tickets
        $this->assertSame($result['validTickets'], $session->get('valid_tickets'));
    }

    public function testProcessPropagatesExceptionFromCsvReader(): void
    {
    $csvReader = $this->createMock(CsvFileReader::class);
    /** @var CsvFileReader $csvReader */
        $csvReader->expects($this->once())
            ->method('openCsvFile')
            ->willThrowException(new \Exception('fail'));

    $userValidator = $this->createMock(UserValidator::class);
    /** @var UserValidator $userValidator */

        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $request = new Request();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $processor = new CsvProcessor($csvReader, $userValidator, $requestStack);

    $dummyFile = $this->getMockBuilder('\Symfony\Component\HttpFoundation\File\UploadedFile')
            ->disableOriginalConstructor()
            ->getMock();
    /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $dummyFile */

        $this->expectException(\Exception::class);
        $processor->process($dummyFile);
    }
}
