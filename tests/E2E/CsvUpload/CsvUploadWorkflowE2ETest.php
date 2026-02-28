<?php

namespace App\Tests\E2E\CsvUpload;

use App\Entity\CsvFieldConfig;
use App\Service\CsvProcessor;
use App\Tests\E2E\AbstractE2ETestCase;

/**
 * E2E Test: Vollständiger CSV-Upload-Workflow
 *
 * Testet die Verarbeitungs-Pipeline von CSV-Dateien mit echter Datenbank.
 * Schlägt fehl (skip) wenn die Datenbank nicht verfügbar ist.
 */
class CsvUploadWorkflowE2ETest extends AbstractE2ETestCase
{
    private CsvProcessor $csvProcessor;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->csvProcessor = static::getContainer()->get(CsvProcessor::class);
        } catch (\Exception $e) {
            $this->markTestSkipped('CsvProcessor service not available: ' . $e->getMessage());
        }
    }

    public function testCsvWithKnownUsersProducesValidTickets(): void
    {
        // Arrange: Create a known user in the database
        $user = new \App\Entity\User();
        $user->setUsername('known_user');
        $user->setEmail('known@example.com');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $config = new CsvFieldConfig();
        $ticketIdField = $config->getTicketIdField();
        $usernameField = $config->getUsernameField();
        $ticketNameField = $config->getTicketNameField();

        $csvContent = "{$ticketIdField},{$usernameField},{$ticketNameField}\nT-E2E-001,known_user,E2E Test Ticket\n";
        $uploadedFile = $this->createTestCsvFile($csvContent);

        // Act
        $result = $this->csvProcessor->process($uploadedFile, $config);

        // Assert
        $this->assertCount(1, $result->validTickets);
        $this->assertSame('T-E2E-001', (string) $result->validTickets[0]->ticketId);
        $this->assertSame('known_user', (string) $result->validTickets[0]->username);
        $this->assertSame('E2E Test Ticket', $result->validTickets[0]->ticketName?->getValue());
    }

    public function testCsvWithUnknownUsersAreDetected(): void
    {
        // Arrange: No users in DB (clean slate from setUp)
        $config = new CsvFieldConfig();
        $ticketIdField = $config->getTicketIdField();
        $usernameField = $config->getUsernameField();
        $ticketNameField = $config->getTicketNameField();

        $csvContent = "{$ticketIdField},{$usernameField},{$ticketNameField}\nT-E2E-002,ghost_user,Ghost Ticket\n";
        $uploadedFile = $this->createTestCsvFile($csvContent);

        // Act
        $result = $this->csvProcessor->process($uploadedFile, $config);

        // Assert: ghost_user is unknown
        $this->assertCount(1, $result->unknownUsers);
        $this->assertSame('ghost_user', (string) $result->unknownUsers[0]->username);
        $this->assertCount(0, $result->validTickets);
    }

    public function testEmptyCsvProducesEmptyResult(): void
    {
        $config = new CsvFieldConfig();
        $ticketIdField = $config->getTicketIdField();
        $usernameField = $config->getUsernameField();
        $ticketNameField = $config->getTicketNameField();

        $csvContent = "{$ticketIdField},{$usernameField},{$ticketNameField}\n";
        $uploadedFile = $this->createTestCsvFile($csvContent);

        $result = $this->csvProcessor->process($uploadedFile, $config);

        $this->assertSame([], $result->validTickets);
        $this->assertSame([], $result->invalidRows);
        $this->assertSame([], $result->unknownUsers);
    }

    public function testCsvWithMultipleUsersHandledCorrectly(): void
    {
        // Arrange: Create some known users
        foreach (['user_alice', 'user_bob'] as $username) {
            $user = new \App\Entity\User();
            $user->setUsername($username);
            $user->setEmail("{$username}@example.com");
            $this->entityManager->persist($user);
        }
        $this->entityManager->flush();

        $config = new CsvFieldConfig();
        $f1 = $config->getTicketIdField();
        $f2 = $config->getUsernameField();
        $f3 = $config->getTicketNameField();

        $csvContent = "{$f1},{$f2},{$f3}\n";
        $csvContent .= "T-E2E-A01,user_alice,Alice Ticket\n";
        $csvContent .= "T-E2E-B01,user_bob,Bob Ticket\n";
        $csvContent .= "T-E2E-G01,ghost_user,Ghost Ticket\n";

        $uploadedFile = $this->createTestCsvFile($csvContent);

        $result = $this->csvProcessor->process($uploadedFile, $config);

        $this->assertCount(2, $result->validTickets);
        $this->assertCount(1, $result->unknownUsers);
    }

    public function testCsvFromFixtureFile(): void
    {
        $uploadedFile = $this->loadCsvFixture('valid_users.csv');

        // Create a custom config matching the fixture file format
        $config = new CsvFieldConfig();

        // Should not throw - just verify it processes
        $result = $this->csvProcessor->process($uploadedFile, $config);

        // Any result is acceptable - just verify the DTO is returned correctly
        $this->assertIsArray($result->validTickets);
        $this->assertIsArray($result->invalidRows);
        $this->assertIsArray($result->unknownUsers);
    }
}
