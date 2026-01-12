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

    public function testGetMonthlyUserStatisticsByTLDReturnsLast6Months(): void
    {
        // Erstelle Testdaten für verschiedene TLDs
        $now = new \DateTime();
        
        // Aktueller Monat: 3 einzigartige Benutzer mit verschiedenen TLDs
        $this->createEmailSent('user1', $now, 'sent', 'user1@company.com');
        $this->createEmailSent('user2', $now, 'sent', 'user2@company.de');
        $this->createEmailSent('user3', $now, 'sent', 'user3@company.com');
        $this->createEmailSent('user1', $now, 'sent', 'user1@company.com'); // Duplikat, sollte nicht gezählt werden
        
        // Vor 1 Monat: 2 Benutzer mit .org TLD
        $oneMonthAgo = (clone $now)->modify('-1 month');
        $this->createEmailSent('user4', $oneMonthAgo, 'sent', 'user4@nonprofit.org');
        $this->createEmailSent('user5', $oneMonthAgo, 'sent', 'user5@foundation.org');
        
        // Vor 2 Monaten: 1 Benutzer mit .uk TLD
        $twoMonthsAgo = (clone $now)->modify('-2 months');
        $this->createEmailSent('user6', $twoMonthsAgo, 'sent', 'user6@company.co.uk');
        
        $this->entityManager->flush();
        
        // Hole die monatlichen TLD-Statistiken
        $statistics = $this->repository->getMonthlyUserStatisticsByTLD();
        
        // Verifiziere, dass genau 6 Monate zurückgegeben werden
        $this->assertCount(6, $statistics);
        
        // Verifiziere die Struktur
        $currentMonthKey = $now->format('Y-m');
        $foundCurrentMonth = false;
        
        foreach ($statistics as $stat) {
            $this->assertArrayHasKey('month', $stat);
            $this->assertArrayHasKey('tld_statistics', $stat);
            $this->assertIsArray($stat['tld_statistics']);
            
            if ($stat['month'] === $currentMonthKey) {
                $foundCurrentMonth = true;
                // Im aktuellen Monat sollten wir 2 .com Benutzer und 1 .de Benutzer haben
                $this->assertArrayHasKey('com', $stat['tld_statistics']);
                $this->assertArrayHasKey('de', $stat['tld_statistics']);
                $this->assertEquals(2, $stat['tld_statistics']['com']); // user1 und user3
                $this->assertEquals(1, $stat['tld_statistics']['de']); // user2
            }
        }
        
        $this->assertTrue($foundCurrentMonth, 'Aktueller Monat sollte in den TLD-Statistiken vorhanden sein');
    }

    public function testGetMonthlyUserStatisticsByTLDIncludesMonthsWithNoData(): void
    {
        // Erstelle nur Daten für den aktuellen Monat
        $now = new \DateTime();
        $this->createEmailSent('user1', $now, 'sent', 'user1@example.com');
        $this->entityManager->flush();
        
        $statistics = $this->repository->getMonthlyUserStatisticsByTLD();
        
        // Alle 6 Monate sollten vorhanden sein, auch wenn keine Daten existieren
        $this->assertCount(6, $statistics);
        
        // Überprüfe, dass Monate ohne Daten leere TLD-Statistiken haben
        $monthsWithEmptyStats = 0;
        foreach ($statistics as $stat) {
            if (empty($stat['tld_statistics'])) {
                $monthsWithEmptyStats++;
            }
        }
        
        $this->assertGreaterThanOrEqual(5, $monthsWithEmptyStats, 'Mindestens 5 Monate sollten keine TLD-Statistiken haben');
    }

    public function testGetMonthlyUserStatisticsByTLDOnlyCountsSuccessfulEmails(): void
    {
        $now = new \DateTime();
        
        // Erfolgreich gesendete E-Mail
        $this->createEmailSent('user1', $now, 'sent', 'user1@example.com');
        
        // Fehlgeschlagene E-Mail (sollte nicht gezählt werden)
        $this->createEmailSent('user2', $now, 'error: SMTP failed', 'user2@example.de');
        
        // Nicht gesendete E-Mail (sollte nicht gezählt werden)
        $this->createEmailSent('user3', $now, 'Nicht versendet (Duplikat)', 'user3@example.org');
        
        $this->entityManager->flush();
        
        $statistics = $this->repository->getMonthlyUserStatisticsByTLD();
        
        $currentMonthKey = $now->format('Y-m');
        foreach ($statistics as $stat) {
            if ($stat['month'] === $currentMonthKey) {
                // Nur user1 mit .com sollte gezählt werden
                $this->assertCount(1, $stat['tld_statistics']);
                $this->assertArrayHasKey('com', $stat['tld_statistics']);
                $this->assertEquals(1, $stat['tld_statistics']['com']);
            }
        }
    }

    public function testGetMonthlyUserStatisticsByTLDGroupsCorrectly(): void
    {
        $now = new \DateTime();
        
        // Erstelle mehrere Benutzer mit verschiedenen TLDs
        $this->createEmailSent('user1', $now, 'sent', 'user1@company.com');
        $this->createEmailSent('user2', $now, 'sent', 'user2@business.com');
        $this->createEmailSent('user3', $now, 'sent', 'user3@firma.de');
        $this->createEmailSent('user4', $now, 'sent', 'user4@unternehmen.de');
        $this->createEmailSent('user5', $now, 'sent', 'user5@organization.org');
        
        $this->entityManager->flush();
        
        $statistics = $this->repository->getMonthlyUserStatisticsByTLD();
        
        $currentMonthKey = $now->format('Y-m');
        foreach ($statistics as $stat) {
            if ($stat['month'] === $currentMonthKey) {
                $this->assertCount(3, $stat['tld_statistics']); // com, de, org
                $this->assertEquals(2, $stat['tld_statistics']['com']);
                $this->assertEquals(2, $stat['tld_statistics']['de']);
                $this->assertEquals(1, $stat['tld_statistics']['org']);
            }
        }
    }

    private function createEmailSent(
        string $username,
        \DateTime $timestamp,
        string $status = 'sent',
        ?string $emailAddress = null
    ): EmailSent {
        $email = new EmailSent();
        $email->setTicketId(TicketId::fromString('T-' . uniqid()));
        $email->setUsername(Username::fromString($username));
        
        // Wenn keine E-Mail-Adresse angegeben ist, verwende username@example.com
        if ($emailAddress === null) {
            $emailAddress = $username . '@example.com';
        }
        $email->setEmail(EmailAddress::fromString($emailAddress));
        
        $email->setSubject('Test Subject');
        $email->setStatus(EmailStatus::fromString($status));
        $email->setTimestamp($timestamp);
        $email->setTestMode(false);
        
        $this->entityManager->persist($email);
        
        return $email;
    }
}
