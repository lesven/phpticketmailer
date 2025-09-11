<?php
namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;
use App\Service\CsvProcessor;
use App\ValueObject\TicketData;

class CsvProcessorSimpleTest extends TestCase
{
    public function testTicketNameIsTruncatedTo50Chars()
    {
        // Dummy-Objekte für die Abhängigkeiten
        $reader = $this->createMock(\App\Service\CsvFileReader::class);
        $userRepository = $this->createMock(\App\Repository\UserRepository::class);
        $requestStack = $this->createMock(\Symfony\Component\HttpFoundation\RequestStack::class);

        $processor = new CsvProcessor($reader, $userRepository, $requestStack);

        $row = [0 => '123', 1 => 'user', 2 => str_repeat('X', 60)];
        $columnIndices = ['ticketId' => 0, 'username' => 1, 'ticketName' => 2];
        $fieldMapping = ['ticketId' => 'ticketId', 'username' => 'username', 'ticketName' => 'ticketName'];

        // Nutze Reflection, um die private Methode zu testen
        $method = new \ReflectionMethod($processor, 'createTicketFromRow');
        $method->setAccessible(true);
        /** @var TicketData $result */
        $result = $method->invoke($processor, $row, $columnIndices, $fieldMapping);

        $this->assertEquals(50, mb_strlen((string) $result->ticketName));
        $this->assertEquals(substr(str_repeat('X', 60), 0, 50), (string) $result->ticketName);
    }
}
