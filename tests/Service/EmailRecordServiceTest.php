<?php

namespace App\Tests\Service;

use App\Entity\EmailSent;
use App\Entity\User;
use App\Event\Email\EmailSkippedEvent;
use App\Repository\UserRepository;
use App\Service\EmailRecordService;
use App\ValueObject\EmailAddress;
use App\ValueObject\EmailStatus;
use App\ValueObject\TicketData;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class EmailRecordServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepo;
    private EventDispatcherInterface $eventDispatcher;
    private LoggerInterface $logger;
    private EmailRecordService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userRepo = $this->createMock(UserRepository::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new EmailRecordService(
            $this->entityManager,
            $this->userRepo,
            $this->eventDispatcher,
            $this->logger,
        );
    }

    public function testCreateSkippedEmailRecordWithUser(): void
    {
        $ticketData = TicketData::fromStrings('SKP-001', 'suser', 'Skip');
        $user = new User();
        $user->setEmail('s@example.com');
        $user->setUsername('suser');

        $now = new \DateTime();
        $rec = $this->service->createSkippedEmailRecord($ticketData, $now, false, EmailStatus::duplicateInCsv(), $user);

        $this->assertInstanceOf(EmailSent::class, $rec);
        $this->assertEquals(\App\ValueObject\TicketId::fromString('SKP-001'), $rec->getTicketId());
        $this->assertEquals('suser', $rec->getUsername());
        $this->assertEquals(EmailAddress::fromString('s@example.com'), $rec->getEmail());
        $this->assertStringContainsString('Mehrfach in CSV', $rec->getStatus()->getValue());
    }

    public function testCreateSkippedEmailRecordWithoutUser(): void
    {
        $ticketData = TicketData::fromStrings('SKP-002', 'nouser', 'Skip');
        $this->userRepo->method('findByUsername')->with('nouser')->willReturn(null);

        $now = new \DateTime();
        $rec = $this->service->createSkippedEmailRecord($ticketData, $now, true, EmailStatus::error('no email found'));

        $this->assertInstanceOf(EmailSent::class, $rec);
        $this->assertNull($rec->getEmail());
        $this->assertTrue($rec->getTestMode());
    }

    public function testCreateSkippedEmailRecordLooksUpUserWhenNotProvided(): void
    {
        $ticketData = TicketData::fromStrings('SKP-003', 'lookupuser', 'Skip');
        $user = new User();
        $user->setEmail('lookup@example.com');
        $user->setUsername('lookupuser');
        $this->userRepo->method('findByUsername')->with('lookupuser')->willReturn($user);

        $now = new \DateTime();
        $rec = $this->service->createSkippedEmailRecord($ticketData, $now, false, EmailStatus::duplicateInCsv());

        $this->assertEquals(EmailAddress::fromString('lookup@example.com'), $rec->getEmail());
    }

    public function testCreateAndDispatchSkippedRecordDispatchesEvent(): void
    {
        $ticketData = TicketData::fromStrings('EVT-001', 'evtuser', 'EventTest');
        $user = new User();
        $user->setEmail('evt@example.com');
        $user->setUsername('evtuser');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(EmailSkippedEvent::class));

        $this->service->createAndDispatchSkippedRecord(
            $ticketData, new \DateTime(), false, EmailStatus::excludedFromSurvey(), $user
        );
    }

    public function testPersistEmailRecordSuccess(): void
    {
        $emailRecord = new EmailSent();
        $emailRecord->setStatus(EmailStatus::sent());

        $this->entityManager->expects($this->once())->method('persist')->with($emailRecord);
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->persistEmailRecord($emailRecord);
        $this->assertSame($emailRecord, $result);
    }

    public function testPersistEmailRecordCreatesErrorFallbackOnFlushFailure(): void
    {
        $emailRecord = new EmailSent();
        $emailRecord->setTicketId(\App\ValueObject\TicketId::fromString('ERR-001'));
        $emailRecord->setUsername('testuser');
        $emailRecord->setTimestamp(new \DateTime());
        $emailRecord->setTestMode(false);
        $emailRecord->setStatus(EmailStatus::sent());
        $emailRecord->setEmail(EmailAddress::fromString('test@example.com'));
        $emailRecord->setSubject('test subject');

        $flushCount = 0;
        $this->entityManager->method('flush')->willReturnCallback(function () use (&$flushCount) {
            $flushCount++;
            if ($flushCount === 1) {
                throw new \Exception('db error');
            }
        });

        $result = $this->service->persistEmailRecord($emailRecord);

        $this->assertStringContainsString('database save failed', $result->getStatus()->getValue());
    }
}
