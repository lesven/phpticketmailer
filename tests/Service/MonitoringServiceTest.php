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
            ->onlyMethods(['checkDatabase', 'checkWebserver', 'checkContainers'])
            ->getMock();
        
        $monitoringService->expects($this->once())
            ->method('checkDatabase')
            ->willReturn(['status' => 'ok', 'tables' => []]);
            
        $monitoringService->expects($this->once())
            ->method('checkWebserver')
            ->willReturn(['status' => 'ok', 'url' => 'http://test.local']);
            
        $monitoringService->expects($this->once())
            ->method('checkContainers')
            ->willReturn(['status' => 'ok', 'containers' => []]);
            
        // Act
        $result = $monitoringService->checkSystemHealth();
        
        // Assert
        $this->assertEquals('ok', $result['status']);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertArrayHasKey('checks', $result);
        $this->assertCount(3, $result['checks']);
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
            ->onlyMethods(['checkDatabase', 'checkWebserver', 'checkContainers'])
            ->getMock();
        
        $monitoringService->expects($this->once())
            ->method('checkDatabase')
            ->willReturn(['status' => 'ok', 'tables' => []]);
            
        $monitoringService->expects($this->once())
            ->method('checkWebserver')
            ->willReturn(['status' => 'error', 'url' => 'http://test.local', 'error' => 'Connection timed out']);
            
        $monitoringService->expects($this->once())
            ->method('checkContainers')
            ->willReturn(['status' => 'ok', 'containers' => []]);
            
        // Act
        $result = $monitoringService->checkSystemHealth();
        
        // Assert
        $this->assertEquals('error', $result['status']);
        $this->assertEquals('error', $result['checks']['webserver']['status']);
    }
}
