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
    private EmailSentRepository $repository;
    private EntityManagerInterface $entityManager;
    private bool $dbAvailable = true;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(EmailSentRepository::class);
        
        // Bereinige die Datenbank vor jedem Test
        $this->clearDatabase();
    }

    protected function tearDown(): void
    {
        if ($this->dbAvailable) {
            $this->clearDatabase();
        }
        parent::tearDown();
    }

    private function clearDatabase(): void
    {
        $connection = $this->entityManager->getConnection();
        try {
            $connection->executeStatement('DELETE FROM emails_sent');
        } catch (\Throwable $e) {
            // If the test DB is not available or the user doesn't have permissions,
            // mark DB as unavailable and skip these tests (only once)
            $this->dbAvailable = false;
            $this->markTestSkipped('Test database not available: ' . $e->getMessage());
        }
    }

    public function testGetMonthlyUserStatisticsReturnsLast6Months(): void
    {
        // Erstelle Testdaten für die letzten 6 Monate
        $now = new \DateTime();
        
        // Monat 0 (aktueller Monat): 3 einzigartige Benutzer
        $this->createEmailSent('user1', $now);
        $this->createEmailSent('user2', $now);
        $this->createEmailSent('user3', $now);
        $this->createEmailSent('user1', $now); // Duplikat, sollte nicht gezählt werden
        
        // Monat -1 (vor 1 Monat): 2 einzigartige Benutzer
        $oneMonthAgo = (clone $now)->modify('-1 month');
        $this->createEmailSent('user4', $oneMonthAgo);
        $this->createEmailSent('user5', $oneMonthAgo);
        
        // Monat -2 (vor 2 Monaten): 1 einzigartiger Benutzer
        $twoMonthsAgo = (clone $now)->modify('-2 months');
        $this->createEmailSent('user6', $twoMonthsAgo);
        
        // Monat -5 (vor 5 Monaten): 1 einzigartiger Benutzer
        $fiveMonthsAgo = (clone $now)->modify('-5 months');
        $this->createEmailSent('user7', $fiveMonthsAgo);
        
        // Monat -7 (vor 7 Monaten): sollte nicht in den letzten 6 Monaten sein
        $sevenMonthsAgo = (clone $now)->modify('-7 months');
        $this->createEmailSent('user8', $sevenMonthsAgo);
        
        $this->entityManager->flush();
        
        // Hole die monatlichen Statistiken
        $statistics = $this->repository->getMonthlyUserStatistics();
        
        // Verifiziere, dass genau 6 Monate zurückgegeben werden
        $this->assertCount(6, $statistics);
        
        // Verifiziere, dass die Monate in aufsteigender Reihenfolge sind
        $previousMonth = null;
        foreach ($statistics as $stat) {
            $this->assertArrayHasKey('month', $stat);
            $this->assertArrayHasKey('unique_users', $stat);
            
            if ($previousMonth !== null) {
                $this->assertGreaterThan($previousMonth, $stat['month']);
            }
            $previousMonth = $stat['month'];
        }
        
        // Verifiziere spezifische Monatswerte
        $currentMonthKey = $now->format('Y-m');
        $foundCurrentMonth = false;
        
        foreach ($statistics as $stat) {
            if ($stat['month'] === $currentMonthKey) {
                $this->assertEquals(3, $stat['unique_users']);
                $foundCurrentMonth = true;
            }
        }
        
        $this->assertTrue($foundCurrentMonth, 'Aktueller Monat sollte in den Statistiken vorhanden sein');
    }

    public function testGetMonthlyUserStatisticsIncludesMonthsWithNoData(): void
    {
        // Erstelle nur Daten für den aktuellen Monat
        $now = new \DateTime();
        $this->createEmailSent('user1', $now);
        $this->entityManager->flush();
        
        $statistics = $this->repository->getMonthlyUserStatistics();
        
        // Alle 6 Monate sollten vorhanden sein, auch wenn keine Daten existieren
        $this->assertCount(6, $statistics);
        
        // Überprüfe, dass Monate ohne Daten 0 Benutzer haben
        $monthsWithZero = 0;
        foreach ($statistics as $stat) {
            if ($stat['unique_users'] === 0) {
                $monthsWithZero++;
            }
        }
        
        $this->assertGreaterThanOrEqual(5, $monthsWithZero, 'Mindestens 5 Monate sollten 0 Benutzer haben');
    }

    public function testGetMonthlyUserStatisticsOnlyCountsSuccessfulEmails(): void
    {
        $now = new \DateTime();
        
        // Erfolgreich gesendete E-Mail
        $this->createEmailSent('user1', $now, 'sent');
        
        // Fehlgeschlagene E-Mail (sollte nicht gezählt werden)
        $this->createEmailSent('user2', $now, 'error: SMTP failed');
        
        // Nicht gesendete E-Mail (sollte nicht gezählt werden)
        $this->createEmailSent('user3', $now, 'Nicht versendet (Duplikat)');
        
        $this->entityManager->flush();
        
        $statistics = $this->repository->getMonthlyUserStatistics();
        
        $currentMonthKey = $now->format('Y-m');
        foreach ($statistics as $stat) {
            if ($stat['month'] === $currentMonthKey) {
                // Nur user1 sollte gezählt werden
                $this->assertEquals(1, $stat['unique_users']);
            }
        }
    }

    private function createEmailSent(
        string $username,
        \DateTime $timestamp,
        string $status = 'sent'
    ): EmailSent {
        $email = new EmailSent();
        $email->setTicketId(TicketId::fromString('T-' . uniqid()));
        $email->setUsername(Username::fromString($username));
        $email->setEmail(EmailAddress::fromString($username . '@example.com'));
        $email->setSubject('Test Subject');
        $email->setStatus(EmailStatus::fromString($status));
        $email->setTimestamp($timestamp);
        $email->setTestMode(false);
        
        $this->entityManager->persist($email);
        
        return $email;
    }

    public function testGetMonthlyUserStatisticsByDomainReturnsLast6Months(): void
    {
        // Erstelle Testdaten für die letzten 6 Monate mit verschiedenen Domains
        $now = new \DateTime();
        
        // Monat 0 (aktueller Monat): 2 Domains mit verschiedenen Benutzern
        $this->createEmailSentWithDomain('user1', 'company-a.com', $now);
        $this->createEmailSentWithDomain('user2', 'company-a.com', $now);
        $this->createEmailSentWithDomain('user3', 'company-b.com', $now);
        $this->createEmailSentWithDomain('user1', 'company-a.com', $now); // Duplikat, sollte nicht gezählt werden
        
        // Monat -1 (vor 1 Monat): Eine andere Domain
        $oneMonthAgo = (clone $now)->modify('-1 month');
        $this->createEmailSentWithDomain('user4', 'company-c.com', $oneMonthAgo);
        $this->createEmailSentWithDomain('user5', 'company-c.com', $oneMonthAgo);
        
        // Monat -2 (vor 2 Monaten): Gemischte Domains
        $twoMonthsAgo = (clone $now)->modify('-2 months');
        $this->createEmailSentWithDomain('user6', 'company-a.com', $twoMonthsAgo);
        $this->createEmailSentWithDomain('user7', 'company-b.com', $twoMonthsAgo);
        
        $this->entityManager->flush();
        
        // Hole die monatlichen Domain-Statistiken
        $statistics = $this->repository->getMonthlyUserStatisticsByDomain();
        
        // Verifiziere, dass genau 6 Monate zurückgegeben werden
        $this->assertCount(6, $statistics);
        
        // Verifiziere, dass die Monate in aufsteigender Reihenfolge sind
        $previousMonth = null;
        foreach ($statistics as $stat) {
            $this->assertArrayHasKey('month', $stat);
            $this->assertArrayHasKey('domains', $stat);
            $this->assertArrayHasKey('total_users', $stat);
            
            if ($previousMonth !== null) {
                $this->assertGreaterThan($previousMonth, $stat['month']);
            }
            $previousMonth = $stat['month'];
        }
        
        // Verifiziere spezifische Monatswerte
        $currentMonthKey = $now->format('Y-m');
        $foundCurrentMonth = false;
        
        foreach ($statistics as $stat) {
            if ($stat['month'] === $currentMonthKey) {
                // Sollte 2 Domains haben: company-a.com mit 2 Benutzern, company-b.com mit 1 Benutzer
                $this->assertArrayHasKey('company-a.com', $stat['domains']);
                $this->assertEquals(2, $stat['domains']['company-a.com']);
                $this->assertArrayHasKey('company-b.com', $stat['domains']);
                $this->assertEquals(1, $stat['domains']['company-b.com']);
                $this->assertEquals(3, $stat['total_users']); // Gesamt: 2 + 1 = 3
                $foundCurrentMonth = true;
            }
        }
        
        $this->assertTrue($foundCurrentMonth, 'Aktueller Monat sollte in den Statistiken vorhanden sein');
    }

    public function testGetMonthlyUserStatisticsByDomainIncludesMonthsWithNoData(): void
    {
        // Erstelle nur Daten für den aktuellen Monat
        $now = new \DateTime();
        $this->createEmailSentWithDomain('user1', 'test.com', $now);
        $this->entityManager->flush();
        
        $statistics = $this->repository->getMonthlyUserStatisticsByDomain();
        
        // Alle 6 Monate sollten vorhanden sein, auch wenn keine Daten existieren
        $this->assertCount(6, $statistics);
        
        // Überprüfe, dass Monate ohne Daten leere Domain-Arrays haben
        $monthsWithEmptyDomains = 0;
        foreach ($statistics as $stat) {
            if (empty($stat['domains'])) {
                $monthsWithEmptyDomains++;
                $this->assertEquals(0, $stat['total_users']);
            }
        }
        
        $this->assertGreaterThanOrEqual(5, $monthsWithEmptyDomains, 'Mindestens 5 Monate sollten keine Domains haben');
    }

    public function testGetMonthlyUserStatisticsByDomainOnlyCountsSuccessfulEmails(): void
    {
        $now = new \DateTime();
        
        // Erfolgreich gesendete E-Mail
        $this->createEmailSentWithDomain('user1', 'success.com', $now, 'sent');
        
        // Fehlgeschlagene E-Mail (sollte nicht gezählt werden)
        $this->createEmailSentWithDomain('user2', 'failed.com', $now, 'error: SMTP failed');
        
        // Nicht gesendete E-Mail (sollte nicht gezählt werden)
        $this->createEmailSentWithDomain('user3', 'skipped.com', $now, 'Nicht versendet (Duplikat)');
        
        $this->entityManager->flush();
        
        $statistics = $this->repository->getMonthlyUserStatisticsByDomain();
        
        $currentMonthKey = $now->format('Y-m');
        foreach ($statistics as $stat) {
            if ($stat['month'] === $currentMonthKey) {
                // Nur success.com sollte vorhanden sein
                $this->assertArrayHasKey('success.com', $stat['domains']);
                $this->assertEquals(1, $stat['domains']['success.com']);
                $this->assertArrayNotHasKey('failed.com', $stat['domains']);
                $this->assertArrayNotHasKey('skipped.com', $stat['domains']);
                $this->assertEquals(1, $stat['total_users']);
            }
        }
    }

    public function testGetMonthlyUserStatisticsByDomainCountsUniqueUsersPerDomain(): void
    {
        $now = new \DateTime();
        
        // Verschiedene Benutzer in derselben Domain
        $this->createEmailSentWithDomain('user1', 'domain.com', $now);
        $this->createEmailSentWithDomain('user2', 'domain.com', $now);
        $this->createEmailSentWithDomain('user1', 'domain.com', $now); // Duplikat Benutzer
        
        $this->entityManager->flush();
        
        $statistics = $this->repository->getMonthlyUserStatisticsByDomain();
        
        $currentMonthKey = $now->format('Y-m');
        foreach ($statistics as $stat) {
            if ($stat['month'] === $currentMonthKey) {
                // Sollte nur 2 einzigartige Benutzer zählen
                $this->assertEquals(2, $stat['domains']['domain.com']);
            }
        }
    }

    private function createEmailSentWithDomain(
        string $username,
        string $domain,
        \DateTime $timestamp,
        string $status = 'sent'
    ): EmailSent {
        $email = new EmailSent();
        $email->setTicketId(TicketId::fromString('T-' . uniqid()));
        $email->setUsername(Username::fromString($username));
        $email->setEmail(EmailAddress::fromString($username . '@' . $domain));
        $email->setSubject('Test Subject');
        $email->setStatus(EmailStatus::fromString($status));
        $email->setTimestamp($timestamp);
        $email->setTestMode(false);
        
        $this->entityManager->persist($email);
        
        return $email;
    }
}
