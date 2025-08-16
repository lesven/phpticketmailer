<?php

namespace App\Tests\Integration;

use App\Service\CsvFileReader;
use App\Service\CsvProcessor;
use App\Service\UserValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class DummyUserValidator extends UserValidator
{
    private array $known;

    public function __construct(array $known)
    {
        $this->known = array_flip($known);
    }

    public function identifyUnknownUsers(array $usernames): array
    {
        $unknown = [];
        foreach (array_keys($usernames) as $username) {
            if (!isset($this->known[$username])) {
                $unknown[] = $username;
            }
        }
        return $unknown;
    }
}

class CsvProcessorTest extends TestCase
{
    public function testProcessCsvFile(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $csvFileReader = new CsvFileReader();
        $userValidator = new DummyUserValidator(['known1', 'known2']);
        $processor = new CsvProcessor($csvFileReader, $userValidator, $requestStack);

        $filePath = __DIR__ . '/../fixtures/tickets.csv';
        $uploaded = new UploadedFile($filePath, 'tickets.csv', 'text/csv', null, true);

        $result = $processor->process($uploaded);

        $this->assertCount(3, $result['validTickets']);
        $this->assertCount(1, $result['invalidRows']);
        $this->assertSame(['unknown1'], $result['unknownUsers']);
        $this->assertSame($result['validTickets'], $session->get('valid_tickets'));
    }
}
