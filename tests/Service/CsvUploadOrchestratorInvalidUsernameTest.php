<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\CsvUploadOrchestrator;
use App\Service\CsvProcessor;
use App\Service\UserCreator;
use App\Repository\CsvFieldConfigRepository;
use App\Service\SessionManager;
use App\ValueObject\Username;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class CsvUploadOrchestratorInvalidUsernameTest extends TestCase
{
    private CsvUploadOrchestrator $orchestrator;
    private CsvProcessor&MockObject $csvProcessor;
    private UserCreator&MockObject $userCreator;
    private CsvFieldConfigRepository&MockObject $csvFieldConfigRepository;
    private SessionManager&MockObject $sessionManager;

    protected function setUp(): void
    {
        $this->csvProcessor = $this->createMock(CsvProcessor::class);
        $this->userCreator = $this->createMock(UserCreator::class);
        $this->csvFieldConfigRepository = $this->createMock(CsvFieldConfigRepository::class);
        $this->sessionManager = $this->createMock(SessionManager::class);
        $statisticsService = $this->createMock(\App\Service\StatisticsService::class);

        $this->orchestrator = new CsvUploadOrchestrator(
            $this->csvProcessor,
            $this->csvFieldConfigRepository,
            $this->sessionManager,
            $this->userCreator,
            $statisticsService
        );
    }

    public function testCreateUsersFromMappingsHandlesInvalidUsernames(): void
    {
        // Setup: Unbekannte User mit ungültigen Benutzernamen
        $unknownUsers = ['valid.user', '.invalid.start', 'invalid.end.', 'another.valid'];
        $emailMappings = [
            'valid.user' => 'valid@example.com',
            '.invalid.start' => 'invalid1@example.com',
            'invalid.end.' => 'invalid2@example.com',
            'another.valid' => 'valid2@example.com'
        ];

        // Session Mock
        $this->sessionManager->expects($this->once())
            ->method('getUnknownUsers')
            ->willReturn($unknownUsers);

        // UserCreator Mock: Wirft Exception für ungültige Benutzernamen
        $this->userCreator->expects($this->exactly(4))
            ->method('createUser')
            ->willReturnCallback(function (string $username, string $email) {
                // Validiere Username - simuliere das gleiche Verhalten wie die echte Klasse
                if (str_starts_with($username, '.') || str_ends_with($username, '.')) {
                    throw new \InvalidArgumentException("Invalid username: {$username}");
                }
                return null; // Erfolgreich für gültige Benutzernamen
            });

        // Erwarte dass persistUsers aufgerufen wird (auch wenn einige User fehlgeschlagen sind)
        $this->userCreator->expects($this->once())
            ->method('persistUsers')
            ->willReturn(2); // 2 gültige User erstellt

        // Test
        $result = $this->orchestrator->processUnknownUsers($emailMappings);

        // Assertions
        $this->assertNotNull($result);
        $this->assertStringContainsString('2 neue Benutzer', $result->message);
    }
}
