<?php
namespace App\Tests\Repository;

use App\Entity\EmailSent;
use App\Repository\EmailSentRepository;
use App\ValueObject\EmailAddress;
use App\ValueObject\EmailStatus;
use App\ValueObject\TicketId;
use App\ValueObject\Username;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EmailSentRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private EmailSentRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
        
        $this->repository = $this->entityManager->getRepository(EmailSent::class);
        
        // Bereinige die Testdatenbank
        $this->cleanDatabase();
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();
        parent::tearDown();
        $this->entityManager->close();
    }

    private function cleanDatabase(): void
    {
        // Lösche alle EmailSent-Einträge für saubere Tests
        $this->entityManager->createQuery('DELETE FROM App\Entity\EmailSent')->execute();
    }

    public function testGetUniqueUsersByMonth(): void
    {
        // Erstelle Test-Daten für verschiedene Monate
        $this->createEmailSent('T-001', 'user1', 'user1@example.com', '2025-01-15');
        $this->createEmailSent('T-002', 'user2', 'user2@example.de', '2025-01-20');
        $this->createEmailSent('T-003', 'user1', 'user1@example.com', '2025-01-25'); // Duplikat user1 in Januar
        $this->createEmailSent('T-004', 'user3', 'user3@example.ch', '2025-02-10');
        $this->createEmailSent('T-005', 'user1', 'user1@example.com', '2025-02-15'); // user1 in Februar

        $results = $this->repository->getUniqueUsersByMonth();

        $this->assertIsArray($results);
        $this->assertEquals(2, $results['2025-01']); // user1 und user2
        $this->assertEquals(2, $results['2025-02']); // user3 und user1
    }

    public function testGetUniqueUsersByMonthAndTLD(): void
    {
        // Erstelle Test-Daten für verschiedene Monate und TLDs
        $this->createEmailSent('T-001', 'user1', 'user1@example.com', '2025-01-15');
        $this->createEmailSent('T-002', 'user2', 'user2@company.de', '2025-01-20');
        $this->createEmailSent('T-003', 'user3', 'user3@test.com', '2025-01-25');
        $this->createEmailSent('T-004', 'user4', 'user4@business.ch', '2025-02-10');
        $this->createEmailSent('T-005', 'user5', 'user5@org.de', '2025-02-15');

        $results = $this->repository->getUniqueUsersByMonthAndTLD();

        $this->assertIsArray($results);
        
        // Januar sollte 2 .com und 1 .de haben
        $this->assertArrayHasKey('2025-01', $results);
        $this->assertEquals(2, $results['2025-01']['com']); // user1 und user3
        $this->assertEquals(1, $results['2025-01']['de']);  // user2
        
        // Februar sollte 1 .ch und 1 .de haben
        $this->assertArrayHasKey('2025-02', $results);
        $this->assertEquals(1, $results['2025-02']['ch']); // user4
        $this->assertEquals(1, $results['2025-02']['de']); // user5
    }

    public function testGetUniqueUsersByMonthAndTLDWithDuplicates(): void
    {
        // Teste dass Duplikate pro Monat/TLD korrekt gezählt werden
        $this->createEmailSent('T-001', 'user1', 'user1@example.com', '2025-01-15');
        $this->createEmailSent('T-002', 'user1', 'user1@example.com', '2025-01-20'); // Duplikat
        $this->createEmailSent('T-003', 'user2', 'user2@test.com', '2025-01-25');

        $results = $this->repository->getUniqueUsersByMonthAndTLD();

        $this->assertArrayHasKey('2025-01', $results);
        $this->assertEquals(2, $results['2025-01']['com']); // user1 und user2 (user1 nur einmal gezählt)
    }

    private function createEmailSent(
        string $ticketId, 
        string $username, 
        string $email, 
        string $timestamp
    ): EmailSent {
        $emailSent = new EmailSent();
        $emailSent->setTicketId(TicketId::fromString($ticketId));
        $emailSent->setUsername(Username::fromString($username));
        $emailSent->setEmail(EmailAddress::fromString($email));
        $emailSent->setStatus(EmailStatus::fromString('sent'));
        $emailSent->setSubject('Test Subject');
        $emailSent->setTimestamp(new \DateTime($timestamp));
        $emailSent->setTestMode(false);

        $this->entityManager->persist($emailSent);
        $this->entityManager->flush();

        return $emailSent;
    }
}
