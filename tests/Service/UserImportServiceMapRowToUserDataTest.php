<?php

namespace App\Tests\Service;

use App\Service\UserCsvHelper;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CSV row mapping.
 *
 * These tests validate that CSV rows are mapped to the expected internal
 * data structure used by the import flow.
 */
class UserImportServiceMapRowToUserDataTest extends TestCase
{
    /**
     * Prüft, dass `mapRowToUserData()` eine CSV-Zeile korrekt in ein
     * assoziatives Array mit den Keys `username` und `email` übersetzt.
     *
     * Ablauf:
     * - Erzeuge eine Beispielzeile und Spaltenindizes.
     * - Rufe die Methode auf und vergleiche das Ergebnis mit den erwarteten Werten.
     *
     * @return void
     */
    public function testMapRowToUserDataProducesExpectedArray(): void
    {
        // Arrange
        $helper = new UserCsvHelper();
        $row = ['ignored', 'alice', 'alice@example.com', 'extra'];
        $columnIndices = ['username' => 1, 'email' => 2];

        // Act
        $result = $helper->mapRowToUserData($row, $columnIndices);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('username', $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertSame('alice', $result['username']);
        $this->assertSame('alice@example.com', $result['email']);
    }
}
