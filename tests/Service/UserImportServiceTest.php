<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserImportService;
use App\Service\CsvFileReader;
use App\Service\CsvValidationService;
use App\Service\UserCsvHelper;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class UserImportServiceTest extends TestCase
{
    private $userRepository;
    private $entityManager;
    private $csvFileReader;
    private $csvValidationService;
    private UserCsvHelper $userCsvHelper;
    private $eventDispatcher;
    private $userImportService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->csvFileReader = $this->createMock(CsvFileReader::class);
        $this->csvValidationService = $this->createMock(CsvValidationService::class);
        $this->userCsvHelper = new UserCsvHelper();
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        
        $this->userImportService = new UserImportService(
            $this->entityManager,
            $this->userRepository,
            $this->csvFileReader,
            $this->csvValidationService,
            $this->userCsvHelper,
            $this->eventDispatcher
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

    public function testImportUsersFromCsvEmptyFileReturnsError(): void
    {
        $uploadedFile = $this->createMock(\Symfony\Component\HttpFoundation\File\UploadedFile::class);

        $this->csvFileReader->expects($this->once())
            ->method('openCsvFile')
            ->with($uploadedFile)
            ->willReturn('handle');

        $this->csvFileReader->expects($this->once())
            ->method('readHeader')
            ->with('handle')
            ->willReturn(['username', 'email']);

        $this->csvFileReader->expects($this->once())
            ->method('validateRequiredColumns')
            ->with(['username', 'email'], ['username', 'email'])
            ->willReturn(['username' => 0, 'email' => 1]);

        // processRows will not invoke the callback -> empty data
        $this->csvFileReader->expects($this->once())
            ->method('processRows')
            ->with('handle', $this->isType('callable'))
            ->willReturnCallback(function ($handle, $cb) {
                // do nothing -> no rows
            });

        $this->csvFileReader->expects($this->once())->method('closeHandle')->with('handle');

        $result = $this->userImportService->importUsersFromCsv($uploadedFile, false);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('leer', mb_strtolower($result->message));
    }

    public function testImportUsersFromCsvValidationFails(): void
    {
        $uploadedFile = $this->createMock(\Symfony\Component\HttpFoundation\File\UploadedFile::class);

        $this->csvFileReader->method('openCsvFile')->willReturn('h');
        $this->csvFileReader->method('readHeader')->willReturn(['username', 'email']);
        $this->csvFileReader->method('validateRequiredColumns')->willReturn(['username' => 0, 'email' => 1]);

        // one bad row
        $this->csvFileReader->expects($this->once())
            ->method('processRows')
            ->with('h', $this->isType('callable'))
            ->willReturnCallback(function ($handle, $cb) {
                $cb(['baduser', 'bademail'], 1);
            });

        $this->csvFileReader->expects($this->once())->method('closeHandle');

        // validation service reports row invalid
        $this->csvValidationService->expects($this->once())
            ->method('validateCsvRow')
            ->with(['username' => 'baduser', 'email' => 'bademail'], ['username', 'email'], 1)
            ->willReturn(['valid' => false, 'errors' => ['Missing @ in email']]);

        $result = $this->userImportService->importUsersFromCsv($uploadedFile, false);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Validierungsfehler', $result->message);
    }

    public function testImportUsersCreatesAndSkipsAndClearExistingWorks(): void
    {
        $uploadedFile = $this->createMock(\Symfony\Component\HttpFoundation\File\UploadedFile::class);

        $this->csvFileReader->method('openCsvFile')->willReturn('h2');
        $this->csvFileReader->method('readHeader')->willReturn(['username', 'email']);
        $this->csvFileReader->method('validateRequiredColumns')->willReturn(['username' => 0, 'email' => 1]);

        // provide two rows: one existing, one new
        $rows = [
            ['existing', 'exist@example.com'],
            ['newuser', 'new@example.com']
        ];

        $this->csvFileReader->expects($this->once())
            ->method('processRows')
            ->with('h2', $this->isType('callable'))
            ->willReturnCallback(function ($handle, $cb) use ($rows) {
                $i = 1;
                foreach ($rows as $r) {
                    $cb($r, $i++);
                }
            });

        $this->csvFileReader->expects($this->once())->method('closeHandle');

        // validation passes for rows
        $this->csvValidationService->method('validateCsvRow')->willReturn(['valid' => true, 'errors' => []]);
        // removeDuplicates returns same list
        $this->csvValidationService->method('removeDuplicates')->willReturnCallback(fn($data, $key) => $data);

        // userRepository: existing user exists, newuser does not
        $this->userRepository->method('findByUsername')
            ->willReturnCallback(function ($username) {
                if ($username === 'existing') {
                    return [new User()];
                }
                return [];
            });


        // Mock QueryBuilder and Query used in clearExistingUsers()
        $qb = $this->getMockBuilder(\Doctrine\ORM\QueryBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $query = $this->getMockBuilder(\Doctrine\ORM\AbstractQuery::class)
            ->disableOriginalConstructor()
            ->getMock();

        $qb->expects($this->any())->method('delete')->willReturnSelf();
        $qb->expects($this->any())->method('getQuery')->willReturn($query);
        $query->expects($this->any())->method('execute')->willReturn(1);

        $this->entityManager->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        // entityManager persist should be called once (for newuser)
        $persisted = 0;
        $this->entityManager->method('persist')
            ->willReturnCallback(function ($user) use (&$persisted) {
                if ($user instanceof User) {
                    $persisted++;
                }
            });

        // flush should be a no-op for this test; allow being called
        $this->entityManager->method('flush')->willReturnCallback(function () use (&$persisted) {
            // noop
        });

        $result = $this->userImportService->importUsersFromCsv($uploadedFile, true);

    $this->assertTrue($result->success);
    $this->assertEquals(2, $result->createdCount, 'expected two users to be created when clearExisting=true');
    $this->assertEquals(2, $persisted, 'persist should have been called for both users');
        // since clearExisting=true the skipped count should be 0
        $this->assertEquals(0, $result->skippedCount);
    }

    public function testImportUsersHandlesPersistExceptionAndCollectsError(): void
    {
        $uploadedFile = $this->createMock(\Symfony\Component\HttpFoundation\File\UploadedFile::class);

        $this->csvFileReader->method('openCsvFile')->willReturn('h3');
        $this->csvFileReader->method('readHeader')->willReturn(['username', 'email']);
        $this->csvFileReader->method('validateRequiredColumns')->willReturn(['username' => 0, 'email' => 1]);

        $this->csvFileReader->expects($this->once())
            ->method('processRows')
            ->with('h3', $this->isType('callable'))
            ->willReturnCallback(function ($handle, $cb) {
                $cb(['badpersist', 'bp@example.com'], 1);
            });

        $this->csvFileReader->expects($this->once())->method('closeHandle');

        $this->csvValidationService->method('validateCsvRow')->willReturn(['valid' => true, 'errors' => []]);
        $this->csvValidationService->method('removeDuplicates')->willReturnArgument(0);

        // make persist throw an exception to simulate DB error
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->willThrowException(new \Exception('DB error'));

        // flush should NOT be called because nothing was created successfully
        $this->entityManager->expects($this->never())->method('flush');

        $result = $this->userImportService->importUsersFromCsv($uploadedFile, false);

        $this->assertTrue($result->success);
        $this->assertGreaterThanOrEqual(1, count($result->errors));
    }
}