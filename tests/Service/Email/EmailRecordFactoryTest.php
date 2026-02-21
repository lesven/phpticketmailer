<?php

namespace App\Tests\Service\Email;

use App\Entity\EmailSent;
use App\Repository\UserRepository;
use App\Service\Email\EmailRecordFactory;
use App\ValueObject\EmailAddress;
use App\ValueObject\EmailStatus;
use App\ValueObject\TicketData;
use App\ValueObject\TicketId;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class EmailRecordFactoryTest extends TestCase
{
    private $userRepo;
    private $entityManager;

    protected function setUp(): void
    {
        $this->userRepo = $this->createMock(UserRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
    }

    public function testCreateSkippedRecordWithUser(): void
    {
        $user = new \App\Entity\User();
        $user->setEmail('skip@example.com');
        $user->setUsername('suser');

        $this->userRepo->method('findByUsername')->with('suser')->willReturn($user);

        $factory = new EmailRecordFactory($this->userRepo, $this->entityManager);
        $ticket = TicketData::fromStrings('SKP-001', 'suser', 'Skip');
        $now = new \DateTime();

        $record = $factory->createSkippedRecord($ticket, $now, false, EmailStatus::duplicateInCsv());

        $this->assertInstanceOf(EmailSent::class, $record);
        $this->assertEquals(TicketId::fromString('SKP-001'), $record->getTicketId());
        $this->assertEquals('suser', $record->getUsername());
        $this->assertEquals(EmailAddress::fromString('skip@example.com'), $record->getEmail());
        $this->assertEquals(EmailStatus::duplicateInCsv(), $record->getStatus());
        $this->assertFalse($record->getTestMode());
    }

    public function testCreateSkippedRecordWithoutUser(): void
    {
        $this->userRepo->method('findByUsername')->willReturn(null);

        $factory = new EmailRecordFactory($this->userRepo, $this->entityManager);
        $ticket = TicketData::fromStrings('SKP-002', 'testuser', 'Skip');
        $now = new \DateTime();

        $record = $factory->createSkippedRecord($ticket, $now, true, EmailStatus::excludedFromSurvey());

        $this->assertNull($record->getEmail());
        $this->assertTrue($record->getTestMode());
    }

    public function testCreateSendRecord(): void
    {
        $factory = new EmailRecordFactory($this->userRepo, $this->entityManager);
        $ticket = TicketData::fromStrings('SND-001', 'sender', 'Send');
        $now = new \DateTime();

        $record = $factory->createSendRecord($ticket, $now, false);

        $this->assertEquals(TicketId::fromString('SND-001'), $record->getTicketId());
        $this->assertEquals('sender', $record->getUsername());
        $this->assertFalse($record->getTestMode());
    }

    public function testPersistSuccess(): void
    {
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $factory = new EmailRecordFactory($this->userRepo, $this->entityManager);
        $record = new EmailSent();
        $record->setTicketId('T-1');
        $record->setUsername('testuser');
        $record->setStatus(EmailStatus::sent());
        $record->setTimestamp(new \DateTime());
        $record->setTestMode(false);
        $record->setSubject('sub');

        $result = $factory->persist($record);

        $this->assertSame($record, $result);
    }

    public function testPersistFallbackOnError(): void
    {
        $callCount = 0;
        $this->entityManager->method('flush')->willReturnCallback(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                throw new \Exception('db error');
            }
        });

        $factory = new EmailRecordFactory($this->userRepo, $this->entityManager);
        $record = new EmailSent();
        $record->setTicketId('T-ERR');
        $record->setUsername('testuser');
        $record->setStatus(EmailStatus::sent());
        $record->setTimestamp(new \DateTime());
        $record->setTestMode(false);
        $record->setEmail('e@example.com');
        $record->setSubject('sub');

        $result = $factory->persist($record);

        $this->assertStringContainsString('database save failed', $result->getStatus()->getValue());
    }
}
