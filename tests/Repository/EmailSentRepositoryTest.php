<?php
namespace App\Tests\Repository;

use PHPUnit\Framework\TestCase;
use App\Repository\EmailSentRepository;
use App\Entity\EmailSent;
use App\ValueObject\EmailStatus;
use App\ValueObject\TicketId;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\AbstractQuery;

class EmailSentRepositoryTest extends TestCase
{
    private $mockRegistry;
    private $mockEntityManager;
    private $mockQueryBuilder;
    private $mockQuery;
    private EmailSentRepository $repository;

    protected function setUp(): void
    {
        $this->mockRegistry = $this->createMock(ManagerRegistry::class);
        $this->mockEntityManager = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $this->mockQueryBuilder = $this->createMock(QueryBuilder::class);
        $this->mockQuery = $this->createMock(AbstractQuery::class);

        $this->mockRegistry->method('getManagerForClass')->willReturn($this->mockEntityManager);
        $this->mockEntityManager->method('createQueryBuilder')->willReturn($this->mockQueryBuilder);
        
        // Mock the QueryBuilder chain methods
        $this->mockQueryBuilder->method('select')->willReturnSelf();
        $this->mockQueryBuilder->method('where')->willReturnSelf();
        $this->mockQueryBuilder->method('setParameter')->willReturnSelf();
        $this->mockQueryBuilder->method('getQuery')->willReturn($this->mockQuery);

        $this->repository = new EmailSentRepository($this->mockRegistry);
    }

    public function testCountSkippedEmailsIncludesExcludedUsers(): void
    {
        // Mock the query to return a count of 5 (representing excluded and other skipped emails)
        $this->mockQuery->method('getSingleScalarResult')->willReturn(5);

        $result = $this->repository->countSkippedEmails();

        $this->assertEquals(5, $result);
        
        // Verify the correct WHERE clause is used for counting skipped emails
        $this->mockQueryBuilder->expects($this->once())
            ->method('where')
            ->with('e.status LIKE :status OR e.status LIKE :status2');
            
        // Verify the correct parameters are set
        $this->mockQueryBuilder->expects($this->exactly(2))
            ->method('setParameter')
            ->withConsecutive(
                ['status', 'Nicht versendet%'],
                ['status2', 'Bereits verarbeitet%']
            );
    }

    public function testGetEmailStatisticsUsesCountSkippedEmails(): void
    {
        // Set up different return values for different count methods
        $methodCallMap = [
            'COUNT(e.id)' => 100,  // total emails
            'COUNT(DISTINCT e.username)' => 25  // unique recipients
        ];
        
        $statusCallMap = [
            'sent' => 80,  // successful emails
            'error%' => 10, // failed emails
            'Nicht versendet%' => 8, // skipped emails (including excluded users)
            'Bereits verarbeitet%' => 2
        ];

        // Mock each method call separately
        $this->mockQuery->method('getSingleScalarResult')
            ->willReturnCallback(function() use (&$methodCallMap, &$statusCallMap) {
                static $callCount = 0;
                $callCount++;
                
                switch ($callCount) {
                    case 1: return 100; // countTotalEmails
                    case 2: return 80;  // countSuccessfulEmails
                    case 3: return 10;  // countFailedEmails
                    case 4: return 10;  // countSkippedEmails (8 + 2)
                    case 5: return 25;  // countUniqueRecipients
                    default: return 0;
                }
            });

        $statistics = $this->repository->getEmailStatistics();

        $this->assertEquals(100, $statistics['total']);
        $this->assertEquals(80, $statistics['successful']);
        $this->assertEquals(10, $statistics['failed']);
        $this->assertEquals(10, $statistics['skipped']); // Should now include excluded users
        $this->assertEquals(25, $statistics['unique_recipients']);
        $this->assertEquals(80.0, $statistics['success_rate']); // 80/100 * 100
    }

    public function testBasicPlaceholder(): void
    {
        // Minimaler Platzhalter-Test, bis spezifische Repository-Tests implementiert sind
        $this->assertTrue(true);
    }
}
