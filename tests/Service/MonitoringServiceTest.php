<?php
namespace App\Tests\Service;

use App\Repository\UserRepository;
use App\Repository\EmailSentRepository;
use App\Repository\CsvFieldConfigRepository;
use App\Service\MonitoringService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class MonitoringServiceTest extends TestCase
{
    private $connection;
    private $userRepository;
    private $emailSentRepository;
    private $csvFieldConfigRepository;
    private $monitoringService;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->emailSentRepository = $this->createMock(EmailSentRepository::class);
        $this->csvFieldConfigRepository = $this->createMock(CsvFieldConfigRepository::class);

        $this->monitoringService = new MonitoringService(
            $this->connection,
            $this->userRepository,
            $this->emailSentRepository,
            $this->csvFieldConfigRepository,
            'http://test.local'
        );
    }

    public function testCheckDatabaseWhenConnectionIsSuccessful()
    {
        // Arrange
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        
        $queryBuilder->expects($this->exactly(3))
            ->method('select')
            ->willReturnSelf();
        
        $queryBuilder->expects($this->exactly(3))
            ->method('getQuery')
            ->willReturn($query);
            
        $query->expects($this->exactly(3))
            ->method('getSingleScalarResult')
            ->willReturn(10); // 10 records in each table
        
        $this->userRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);
            
        $this->emailSentRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);
            
        $this->csvFieldConfigRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        // Act
        $result = $this->monitoringService->checkDatabase();

        // Assert
        $this->assertEquals('ok', $result['status']);
        $this->assertCount(3, $result['tables']);
        $this->assertEquals('ok', $result['tables']['users']['status']);
        $this->assertEquals(10, $result['tables']['users']['recordCount']);
    }

    public function testCheckDatabaseWhenConnectionFails()
    {
        // Arrange
        $this->connection->expects($this->once())
            ->method('connect')
            ->willThrowException(new \Doctrine\DBAL\Exception('Connection failed'));

        // Act
        $result = $this->monitoringService->checkDatabase();

        // Assert
        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('Datenbankverbindung fehlgeschlagen', $result['error']);
    }    public function testCheckSystemHealth()
    {
        // Mock the component check methods
        $monitoringService = $this->getMockBuilder(MonitoringService::class)
            ->setConstructorArgs([
                $this->connection,
                $this->userRepository,
                $this->emailSentRepository,
                $this->csvFieldConfigRepository,
                'http://test.local'
            ])
            ->onlyMethods(['checkDatabase'])
            ->getMock();

        $monitoringService->expects($this->once())
            ->method('checkDatabase')
            ->willReturn(['status' => 'ok', 'tables' => []]);

        // Act
        $result = $monitoringService->checkSystemHealth();

        // Assert
        $this->assertEquals('ok', $result['status']);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertArrayHasKey('checks', $result);
        $this->assertArrayHasKey('database', $result['checks']);
    }    public function testCheckSystemHealthWhenComponentFails()
    {
        // Mock the component check methods
        $monitoringService = $this->getMockBuilder(MonitoringService::class)
            ->setConstructorArgs([
                $this->connection,
                $this->userRepository,
                $this->emailSentRepository,
                $this->csvFieldConfigRepository,
                'http://test.local'
            ])
            ->onlyMethods(['checkDatabase'])
            ->getMock();

        $monitoringService->expects($this->once())
            ->method('checkDatabase')
            ->willReturn(['status' => 'error', 'tables' => [], 'error' => 'Datenbankfehler']);

        // Act
        $result = $monitoringService->checkSystemHealth();

        // Assert
        $this->assertEquals('error', $result['status']);
        $this->assertEquals('error', $result['checks']['database']['status']);
    }

    public function testConstructorDefaultBaseUrlIsSet(): void
    {
        $svc = new \App\Service\MonitoringService(
            $this->connection,
            $this->userRepository,
            $this->emailSentRepository,
            $this->csvFieldConfigRepository
        );

        $ref = new \ReflectionClass($svc);
        $prop = $ref->getProperty('baseUrl');
        $prop->setAccessible(true);

        $this->assertSame('http://localhost:8090', $prop->getValue($svc));
    }

    public function testCheckSystemHealthReturnsDateTimeTimestamp(): void
    {
        $this->connection->expects($this->once())->method('connect');
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $query->method('getSingleScalarResult')->willReturn(0);

        $this->userRepository->method('createQueryBuilder')->willReturn($queryBuilder);
        $this->emailSentRepository->method('createQueryBuilder')->willReturn($queryBuilder);
        $this->csvFieldConfigRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $res = $this->monitoringService->checkSystemHealth();

        $this->assertArrayHasKey('timestamp', $res);
        $this->assertInstanceOf(\DateTime::class, $res['timestamp']);
    }

    public function testCheckDatabaseWhenCreateQueryBuilderThrows()
    {
        $this->connection->expects($this->once())->method('connect');

        $exc = new \Exception('qb fail');
        $this->userRepository->method('createQueryBuilder')->will($this->throwException($exc));

        $query = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        $query->method('getSingleScalarResult')->willReturn(0);
        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->emailSentRepository->method('createQueryBuilder')->willReturn($qb);
        $this->csvFieldConfigRepository->method('createQueryBuilder')->willReturn($qb);

        $res = $this->monitoringService->checkDatabase();

        $this->assertSame('error', $res['status']);
        $this->assertSame('error', $res['tables']['users']['status']);
        $this->assertStringContainsString('qb fail', $res['tables']['users']['error']);
    }

    public function testCheckDatabaseWhenRecordCountIsNull()
    {
        $this->connection->expects($this->once())->method('connect');

        $query = $this->createMock(\Doctrine\ORM\AbstractQuery::class);
        $query->method('getSingleScalarResult')->willReturn(null);
        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->userRepository->method('createQueryBuilder')->willReturn($qb);
        $this->emailSentRepository->method('createQueryBuilder')->willReturn($qb);
        $this->csvFieldConfigRepository->method('createQueryBuilder')->willReturn($qb);

        $res = $this->monitoringService->checkDatabase();

        $this->assertSame('ok', $res['status']);
        $this->assertArrayHasKey('users', $res['tables']);
        $this->assertArrayHasKey('recordCount', $res['tables']['users']);
        $this->assertNull($res['tables']['users']['recordCount']);
    }
}
