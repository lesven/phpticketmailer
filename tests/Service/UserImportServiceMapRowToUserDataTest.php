<?php

namespace App\Tests\Service;

use App\Service\UserImportService;
use PHPUnit\Framework\TestCase;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\UserRepository;
use App\Service\CsvFileReader;
use App\Service\CsvValidationService;
use App\Service\UserValidator;

class UserImportServiceMapRowToUserDataTest extends TestCase
{
    /**
     * Testet, dass mapRowToUserData eine CSV-Zeile korrekt auf das interne Format mappt.
     */
    public function testMapRowToUserDataProducesExpectedArray()
    {
        // Mocks für Konstruktor-Parameter, keine Methoden werden hier benötigt
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(UserRepository::class);
        $csvReader = $this->createMock(CsvFileReader::class);
        $csvValidation = $this->createMock(CsvValidationService::class);
        $userValidator = $this->createMock(UserValidator::class);

        $service = new UserImportService($em, $repo, $csvReader, $csvValidation, $userValidator);

        // Beispielzeile und Spaltenindizes
        $row = ['ignored', 'alice', 'alice@example.com', 'extra'];
        $columnIndices = ['username' => 1, 'email' => 2];

        // Reflection, um die private Methode aufzurufen
        $refClass = new \ReflectionClass(UserImportService::class);
        $method = $refClass->getMethod('mapRowToUserData');
        $method->setAccessible(true);

        $result = $method->invokeArgs($service, [$row, $columnIndices]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('username', $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertSame('alice', $result['username']);
        $this->assertSame('alice@example.com', $result['email']);
    }
}
