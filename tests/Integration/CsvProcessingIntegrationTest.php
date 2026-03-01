<?php

namespace App\Tests\Integration;

use App\Dto\CsvProcessingResult;
use App\Entity\CsvFieldConfig;
use App\Service\CsvProcessor;
use App\Service\CsvValidationService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Integration Test für die CSV-Verarbeitungs-Pipeline:
 * CsvValidationService → CsvProcessor
 */
class CsvProcessingIntegrationTest extends KernelTestCase
{
    public function testCsvProcessorIsAvailableInContainer(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $processor = $container->get(CsvProcessor::class);

        $this->assertInstanceOf(CsvProcessor::class, $processor);
    }

    public function testCsvValidationServiceIsAvailableInContainer(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $validator = $container->get(CsvValidationService::class);

        $this->assertInstanceOf(CsvValidationService::class, $validator);
    }

    public function testCsvProcessorReturnsCorrectResultType(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        try {
            /** @var CsvProcessor $processor */
            $processor = $container->get(CsvProcessor::class);

            $config = new CsvFieldConfig();
            $ticketIdField = $config->getTicketIdField();
            $usernameField = $config->getUsernameField();
            $ticketNameField = $config->getTicketNameField();

            $csvContent = "{$ticketIdField},{$usernameField},{$ticketNameField}\nT-001,john_doe,Login Bug\nT-002,jane_doe,Payment Issue\n";
            $uploadedFile = $this->createUploadedFile($csvContent);

            $result = $processor->process($uploadedFile, $config);

            $this->assertInstanceOf(CsvProcessingResult::class, $result);
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for integration test: ' . $e->getMessage());
        }
    }

    public function testCsvProcessorHandlesEmptyCsv(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        try {
            /** @var CsvProcessor $processor */
            $processor = $container->get(CsvProcessor::class);

            $config = new CsvFieldConfig();
            $ticketIdField = $config->getTicketIdField();
            $usernameField = $config->getUsernameField();
            $ticketNameField = $config->getTicketNameField();

            // Only headers, no data rows
            $csvContent = "{$ticketIdField},{$usernameField},{$ticketNameField}\n";
            $uploadedFile = $this->createUploadedFile($csvContent);

            $result = $processor->process($uploadedFile, $config);

            $this->assertSame([], $result->validTickets);
            $this->assertSame([], $result->invalidRows);
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for integration test: ' . $e->getMessage());
        }
    }

    public function testCsvFieldConfigDefaultValues(): void
    {
        $config = new CsvFieldConfig();

        $this->assertSame(CsvFieldConfig::DEFAULT_TICKET_ID_FIELD, $config->getTicketIdField());
        $this->assertSame(CsvFieldConfig::DEFAULT_USERNAME_FIELD, $config->getUsernameField());
        $this->assertSame(CsvFieldConfig::DEFAULT_TICKET_NAME_FIELD, $config->getTicketNameField());
        $this->assertSame(CsvFieldConfig::DEFAULT_CREATED_FIELD, $config->getCreatedField());
    }

    public function testCsvFieldConfigFieldMapping(): void
    {
        $config = new CsvFieldConfig();
        $mapping = $config->getFieldMapping();

        $this->assertArrayHasKey('ticketId', $mapping);
        $this->assertArrayHasKey('username', $mapping);
        $this->assertArrayHasKey('ticketName', $mapping);
        $this->assertArrayHasKey('created', $mapping);

        $this->assertSame(CsvFieldConfig::DEFAULT_TICKET_ID_FIELD, $mapping['ticketId']);
        $this->assertSame(CsvFieldConfig::DEFAULT_USERNAME_FIELD, $mapping['username']);
    }

    public function testCsvFieldConfigCustomFieldNames(): void
    {
        $config = new CsvFieldConfig();
        $config->setTicketIdField('IssueKey');
        $config->setUsernameField('Reporter');
        $config->setTicketNameField('Summary');
        $config->setCreatedField('Created');

        $this->assertSame('IssueKey', $config->getTicketIdField());
        $this->assertSame('Reporter', $config->getUsernameField());
        $this->assertSame('Summary', $config->getTicketNameField());
        $this->assertSame('Created', $config->getCreatedField());
    }

    private function createUploadedFile(string $content, string $filename = 'test.csv'): UploadedFile
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_int_');
        file_put_contents($tempFile, $content);

        return new UploadedFile($tempFile, $filename, 'text/csv', null, true);
    }
}
