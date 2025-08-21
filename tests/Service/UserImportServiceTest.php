<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserImportService;
use App\Service\CsvFileReader;
use App\Service\CsvValidationService;
use App\Service\UserValidator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class UserImportServiceTest extends TestCase
{
    private $userRepository;
    private $entityManager;
    private $csvFileReader;
    private $csvValidationService;
    private $userValidator;
    private $userImportService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->csvFileReader = $this->createMock(CsvFileReader::class);
        $this->csvValidationService = $this->createMock(CsvValidationService::class);
        $this->userValidator = $this->createMock(UserValidator::class);
        
        $this->userImportService = new UserImportService(
            $this->entityManager,
            $this->userRepository,
            $this->csvFileReader,
            $this->csvValidationService,
            $this->userValidator
        );
    }

    public function testExportUsersToCsvHasCorrectHeaders(): void
    {
        // Create mock users
        $user1 = new User();
        $user1->setUsername('testuser1');
        $user1->setEmail('test1@example.com');
        
        $user2 = new User();
        $user2->setUsername('testuser2'); 
        $user2->setEmail('test2@example.com');

        // Use reflection to set IDs since they're normally auto-generated
        $reflection1 = new \ReflectionClass($user1);
        $idProperty1 = $reflection1->getProperty('id');
        $idProperty1->setAccessible(true);
        $idProperty1->setValue($user1, 1);

        $reflection2 = new \ReflectionClass($user2);
        $idProperty2 = $reflection2->getProperty('id');
        $idProperty2->setAccessible(true);
        $idProperty2->setValue($user2, 2);

        $this->userRepository->method('findAll')->willReturn([$user1, $user2]);

        $csvContent = $this->userImportService->exportUsersToCsv();
        
        // Split into lines
        $lines = explode("\n", trim($csvContent));
        
        // Check header line - this should match what import expects
        $header = $lines[0];
        $this->assertEquals('ID,username,email', $header, 'Export header should match import expectations');
        
        // Check data lines
        $this->assertCount(3, $lines); // header + 2 data lines
        $this->assertStringContainsString('1,"testuser1","test1@example.com"', $lines[1]);
        $this->assertStringContainsString('2,"testuser2","test2@example.com"', $lines[2]);
    }
}