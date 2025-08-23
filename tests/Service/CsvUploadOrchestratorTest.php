<?php

namespace App\Tests\Service;

use App\Service\CsvUploadOrchestrator;
use App\Service\CsvProcessor;
use App\Repository\CsvFieldConfigRepository;
use App\Service\SessionManager;
use App\Service\UserCreator;
use App\Service\UploadResult;
use App\Service\UnknownUsersResult;
use App\Entity\CsvFieldConfig;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use PHPUnit\Framework\TestCase;

class CsvUploadOrchestratorTest extends TestCase
{
    private CsvUploadOrchestrator $orchestrator;
    private CsvProcessor $csvProcessor;
    private CsvFieldConfigRepository $csvFieldConfigRepository;
    private SessionManager $sessionManager;
    private UserCreator $userCreator;

    protected function setUp(): void
    {
        $this->csvProcessor = $this->createMock(CsvProcessor::class);
        $this->csvFieldConfigRepository = $this->createMock(CsvFieldConfigRepository::class);
        $this->sessionManager = $this->createMock(SessionManager::class);
        $this->userCreator = $this->createMock(UserCreator::class);

        $this->orchestrator = new CsvUploadOrchestrator(
            $this->csvProcessor,
            $this->csvFieldConfigRepository,
            $this->sessionManager,
            $this->userCreator
        );
    }

    public function testProcessUploadWithUnknownUsersRedirectsToUnknownUsers(): void
    {
        $csvFile = $this->createMockUploadedFile();
        $csvFieldConfig = $this->createMockCsvFieldConfig();
        
        $processingResult = [
            'unknownUsers' => ['user1', 'user2', 'user3'],
            'validTickets' => [
                ['ticketId' => 'T-001', 'username' => 'known_user']
            ]
        ];

        $this->csvFieldConfigRepository->expects($this->once())
            ->method('saveConfig')
            ->with($csvFieldConfig);

        $this->csvProcessor->expects($this->once())
            ->method('process')
            ->with($csvFile, $csvFieldConfig)
            ->willReturn($processingResult);

        $this->sessionManager->expects($this->once())
            ->method('storeUploadResults')
            ->with($processingResult);

        $result = $this->orchestrator->processUpload($csvFile, true, false, $csvFieldConfig);

        $this->assertInstanceOf(UploadResult::class, $result);
        $this->assertEquals('unknown_users', $result->redirectRoute);
        $this->assertEquals(['testMode' => 1, 'forceResend' => 0], $result->routeParameters);
        $this->assertEquals('Es wurden 3 unbekannte Benutzer gefunden', $result->flashMessage);
        $this->assertEquals('info', $result->flashType);
    }

    public function testProcessUploadWithoutUnknownUsersRedirectsToEmailSending(): void
    {
        $csvFile = $this->createMockUploadedFile();
        $csvFieldConfig = $this->createMockCsvFieldConfig();
        
        $processingResult = [
            'unknownUsers' => [],
            'validTickets' => [
                ['ticketId' => 'T-001', 'username' => 'known_user1'],
                ['ticketId' => 'T-002', 'username' => 'known_user2']
            ]
        ];

        $this->csvFieldConfigRepository->expects($this->once())
            ->method('saveConfig')
            ->with($csvFieldConfig);

        $this->csvProcessor->expects($this->once())
            ->method('process')
            ->with($csvFile, $csvFieldConfig)
            ->willReturn($processingResult);

        $this->sessionManager->expects($this->once())
            ->method('storeUploadResults')
            ->with($processingResult);

        $result = $this->orchestrator->processUpload($csvFile, false, true, $csvFieldConfig);

        $this->assertInstanceOf(UploadResult::class, $result);
        $this->assertEquals('send_emails', $result->redirectRoute);
        $this->assertEquals(['testMode' => 0, 'forceResend' => 1], $result->routeParameters);
        $this->assertEquals('CSV-Datei erfolgreich verarbeitet', $result->flashMessage);
        $this->assertEquals('success', $result->flashType);
    }

    public function testProcessUploadWithTestModeAndForceResendParameters(): void
    {
        $csvFile = $this->createMockUploadedFile();
        $csvFieldConfig = $this->createMockCsvFieldConfig();
        
        $processingResult = [
            'unknownUsers' => ['unknown1'],
            'validTickets' => []
        ];

        $this->csvProcessor->method('process')->willReturn($processingResult);

        $result = $this->orchestrator->processUpload($csvFile, true, true, $csvFieldConfig);

        $this->assertEquals(['testMode' => 1, 'forceResend' => 1], $result->routeParameters);
    }

    public function testProcessUnknownUsersWithValidEmailMappings(): void
    {
        $unknownUsers = ['user1', 'user2', 'user3'];
        $emailMappings = [
            'user1' => 'user1@example.com',
            'user2' => 'user2@example.com',
            'user3' => 'user3@example.com'
        ];

        $this->sessionManager->expects($this->once())
            ->method('getUnknownUsers')
            ->willReturn($unknownUsers);

        $this->userCreator->method('createUser')
            ->willReturnCallback(function ($username, $email) {
                // Verify correct parameters are passed
                $this->assertContainsEquals($username, ['user1', 'user2', 'user3']);
                $this->assertStringEndsWith('@example.com', $email);
            });

        $this->userCreator->expects($this->once())
            ->method('persistUsers')
            ->willReturn(3);

        $result = $this->orchestrator->processUnknownUsers($emailMappings);

        $this->assertInstanceOf(UnknownUsersResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertEquals(3, $result->newUsersCount);
        $this->assertEquals('Neue Benutzer wurden erfolgreich angelegt', $result->message);
        $this->assertEquals('success', $result->flashType);
    }

    public function testProcessUnknownUsersWithPartialEmailMappings(): void
    {
        $unknownUsers = ['user1', 'user2', 'user3'];
        $emailMappings = [
            'user1' => 'user1@example.com',
            // user2 fehlt absichtlich
            'user3' => 'user3@example.com'
        ];

        $this->sessionManager->expects($this->once())
            ->method('getUnknownUsers')
            ->willReturn($unknownUsers);

        $this->userCreator->expects($this->exactly(2))
            ->method('createUser');

        $this->userCreator->expects($this->once())
            ->method('persistUsers')
            ->willReturn(2);

        $result = $this->orchestrator->processUnknownUsers($emailMappings);

        $this->assertTrue($result->success);
        $this->assertEquals(2, $result->newUsersCount);
    }

    public function testProcessUnknownUsersWithEmptyUnknownUsers(): void
    {
        $emailMappings = ['user1' => 'user1@example.com'];

        $this->sessionManager->expects($this->once())
            ->method('getUnknownUsers')
            ->willReturn([]);

        $this->userCreator->expects($this->never())
            ->method('createUser');

        $this->userCreator->expects($this->never())
            ->method('persistUsers');

        $result = $this->orchestrator->processUnknownUsers($emailMappings);

        $this->assertInstanceOf(UnknownUsersResult::class, $result);
        $this->assertFalse($result->success);
        $this->assertEquals(0, $result->newUsersCount);
        $this->assertEquals('Keine unbekannten Benutzer zu verarbeiten', $result->message);
        $this->assertEquals('warning', $result->flashType);
    }

    public function testProcessUnknownUsersWithEmptyEmailMappings(): void
    {
        $unknownUsers = ['user1', 'user2'];
        $emailMappings = [];

        $this->sessionManager->expects($this->once())
            ->method('getUnknownUsers')
            ->willReturn($unknownUsers);

        $this->userCreator->expects($this->never())
            ->method('createUser');

        $this->userCreator->expects($this->once())
            ->method('persistUsers')
            ->willReturn(0);

        $result = $this->orchestrator->processUnknownUsers($emailMappings);

        $this->assertTrue($result->success);
        $this->assertEquals(0, $result->newUsersCount);
    }

    public function testProcessUploadHandlesAllStepsInCorrectOrder(): void
    {
        $csvFile = $this->createMockUploadedFile();
        $csvFieldConfig = $this->createMockCsvFieldConfig();
        $processingResult = ['unknownUsers' => [], 'validTickets' => []];

        // Verify the order of operations
        $callOrder = [];

        $this->csvFieldConfigRepository->expects($this->once())
            ->method('saveConfig')
            ->willReturnCallback(function() use (&$callOrder) {
                $callOrder[] = 'saveConfig';
            });

        $this->csvProcessor->expects($this->once())
            ->method('process')
            ->willReturnCallback(function() use (&$callOrder, $processingResult) {
                $callOrder[] = 'process';
                return $processingResult;
            });

        $this->sessionManager->expects($this->once())
            ->method('storeUploadResults')
            ->willReturnCallback(function() use (&$callOrder) {
                $callOrder[] = 'storeUploadResults';
            });

        $this->orchestrator->processUpload($csvFile, false, false, $csvFieldConfig);

        $this->assertEquals(['saveConfig', 'process', 'storeUploadResults'], $callOrder);
    }

    public function testConstructorAcceptsDependencies(): void
    {
        $csvProcessor = $this->createMock(CsvProcessor::class);
        $repository = $this->createMock(CsvFieldConfigRepository::class);
        $sessionManager = $this->createMock(SessionManager::class);
        $userCreator = $this->createMock(UserCreator::class);

        $orchestrator = new CsvUploadOrchestrator(
            $csvProcessor,
            $repository,
            $sessionManager,
            $userCreator
        );

        $this->assertInstanceOf(CsvUploadOrchestrator::class, $orchestrator);
    }

    public function testProcessUnknownUsersWorkflowIntegration(): void
    {
        // Test complete workflow from session data to user creation
        $unknownUsers = ['newuser1', 'newuser2'];
        $emailMappings = [
            'newuser1' => 'newuser1@company.com',
            'newuser2' => 'newuser2@company.com'
        ];

        $this->sessionManager->expects($this->once())
            ->method('getUnknownUsers')
            ->willReturn($unknownUsers);

        $this->userCreator->expects($this->exactly(2))
            ->method('createUser');

        $this->userCreator->expects($this->once())
            ->method('persistUsers')
            ->willReturn(2);

        $result = $this->orchestrator->processUnknownUsers($emailMappings);

        $this->assertTrue($result->success);
        $this->assertEquals(2, $result->newUsersCount);
        $this->assertEquals('success', $result->flashType);
    }

    private function createMockUploadedFile(): UploadedFile
    {
        return $this->createMock(UploadedFile::class);
    }

    private function createMockCsvFieldConfig(): CsvFieldConfig
    {
        return $this->createMock(CsvFieldConfig::class);
    }
}
