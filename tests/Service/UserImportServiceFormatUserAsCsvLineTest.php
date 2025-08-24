<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\UserCsvHelper;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CSV formatting helper via the public API.
 *
 * These tests ensure that a User entity is serialized into the expected
 * CSV line format (ID,username,email) and that fields are escaped
 * correctly according to the project's CSV conventions.
 */
class UserImportServiceFormatUserAsCsvLineTest extends TestCase
{
    /**
     * Prüft, dass `formatUserAsCsvLine()` eine korrekte CSV-Zeile zurückgibt.
     *
     * Ablauf:
     * - Erzeuge einen User mit Username/Email und setze die ID per Reflection.
     * - Rufe die Hilfsmethode auf und vergleiche das Ergebnis mit dem
     *   exakt erwarteten String inklusive Newline.
     *
     * @return void
     */
    public function testFormatUserAsCsvLineProducesCorrectString(): void
    {
        $helper = new UserCsvHelper();

        $user = new User();
        $user->setUsername('csvuser');
        $user->setEmail('csv@example.com');

        // set id via reflection
        $refU = new \ReflectionClass($user);
        $idProp = $refU->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($user, 42);

        $line = $helper->formatUserAsCsvLine($user);

        $this->assertSame("42,\"csvuser\",\"csv@example.com\"\n", $line);
    }
}
