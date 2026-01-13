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
                // Wir erwarten jetzt absteigende Reihenfolge (aktuellster Monat zuerst)
                $this->assertLessThan($previousMonth, $stat['month']);
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

    public function testGetMonthlyUserStatisticsByDomainOrdersDomainsByCountDesc(): void
    {
        $now = new \DateTime();

        // Erzeuge Verteilungen: a:3, c:2, b:1
        $this->createEmailSentWithDomain('u1', 'company-a.com', $now);
        $this->createEmailSentWithDomain('u2', 'company-a.com', $now);
        $this->createEmailSentWithDomain('u3', 'company-a.com', $now);

        $this->createEmailSentWithDomain('v1', 'company-c.com', $now);
        $this->createEmailSentWithDomain('v2', 'company-c.com', $now);

        $this->createEmailSentWithDomain('w1', 'company-b.com', $now);

        $this->entityManager->flush();

        $statistics = $this->repository->getMonthlyUserStatisticsByDomain();

        $currentMonthKey = $now->format('Y-m');
        foreach ($statistics as $stat) {
            if ($stat['month'] === $currentMonthKey) {
                $domains = array_keys($stat['domains']);
                // Erwartete Reihenfolge: company-a.com, company-c.com, company-b.com
                $this->assertSame(['company-a.com', 'company-c.com', 'company-b.com'], $domains);
            }
        }
    }

    public function testGetMonthlyUserStatisticsByDomainNormalizesAndFiltersEmails(): void
    {
        $now = new \DateTime();
        $conn = $this->entityManager->getConnection();

        // Füge rohe DB-Zeilen ein, die nicht per EmailAddress normalisiert wurden
        $conn->executeStatement(
            'INSERT INTO emails_sent (ticket_id, username, email, subject, status, timestamp, test_mode, ticket_name) VALUES (:ticket_id, :username, :email, :subject, :status, :timestamp, :test_mode, :ticket_name)',
            [
                'ticket_id' => 'DBTEST-01',
                'username' => 'user_upper',
                'email' => 'User@Example.COM ', // mixed case + trailing space
                'subject' => 'Test',
                'status' => 'sent',
                'timestamp' => $now->format('Y-m-d H:i:s'),
                'test_mode' => 0,
                'ticket_name' => null,
            ]
        );

        $conn->executeStatement(
            'INSERT INTO emails_sent (ticket_id, username, email, subject, status, timestamp, test_mode, ticket_name) VALUES (:ticket_id, :username, :email, :subject, :status, :timestamp, :test_mode, :ticket_name)',
            [
                'ticket_id' => 'DBTEST-02',
                'username' => 'user_lower',
                'email' => 'other@example.com',
                'subject' => 'Test',
                'status' => 'sent',
                'timestamp' => $now->format('Y-m-d H:i:s'),
                'test_mode' => 0,
                'ticket_name' => null,
            ]
        );

        // Ungültige Email (kein @) -> sollte gefiltert werden
        $conn->executeStatement(
            'INSERT INTO emails_sent (ticket_id, username, email, subject, status, timestamp, test_mode, ticket_name) VALUES (:ticket_id, :username, :email, :subject, :status, :timestamp, :test_mode, :ticket_name)',
            [
                'ticket_id' => 'DBTEST-03',
                'username' => 'invalid_user',
                'email' => 'invalidemail',
                'subject' => 'Test',
                'status' => 'sent',
                'timestamp' => $now->format('Y-m-d H:i:s'),
                'test_mode' => 0,
                'ticket_name' => null,
            ]
        );

        $this->entityManager->flush();

        $statistics = $this->repository->getMonthlyUserStatisticsByDomain();

        $currentMonthKey = $now->format('Y-m');
        $found = false;
        foreach ($statistics as $stat) {
            if ($stat['month'] === $currentMonthKey) {
                $this->assertArrayHasKey('example.com', $stat['domains']);
                // user_upper + other -> zwei eindeutige Benutzer in example.com
                $this->assertEquals(2, $stat['domains']['example.com']);
                // invalidemail darf nicht auftauchen
                $this->assertArrayNotHasKey('invalidemail', $stat['domains']);
                $found = true;
            }
        }

        $this->assertTrue($found, 'Aktueller Monat sollte in den Statistiken vorhanden sein');
    }

    public function testGetMonthlyTicketStatisticsByDomainReturnsLast6Months(): void
    {
        // Erstelle Testdaten für die letzten 6 Monate mit verschiedenen Domains und Tickets
        $now = new \DateTime();
        
        // Monat 0 (aktueller Monat): 2 Domains mit verschiedenen Tickets
        $this->createEmailSentWithDomainAndTicket('user1', 'company-a.com', 'T-001', $now);
        $this->createEmailSentWithDomainAndTicket('user2', 'company-a.com', 'T-002', $now);
        $this->createEmailSentWithDomainAndTicket('user3', 'company-b.com', 'T-003', $now);
        $this->createEmailSentWithDomainAndTicket('user1', 'company-a.com', 'T-001', $now); // Duplikat Ticket, sollte nicht gezählt werden
        
        // Monat -1 (vor 1 Monat): Eine andere Domain
        $oneMonthAgo = (clone $now)->modify('-1 month');
        $this->createEmailSentWithDomainAndTicket('user4', 'company-c.com', 'T-004', $oneMonthAgo);
        $this->createEmailSentWithDomainAndTicket('user5', 'company-c.com', 'T-005', $oneMonthAgo);
        
        // Monat -2 (vor 2 Monaten): Gemischte Domains
        $twoMonthsAgo = (clone $now)->modify('-2 months');
        $this->createEmailSentWithDomainAndTicket('user6', 'company-a.com', 'T-006', $twoMonthsAgo);
        $this->createEmailSentWithDomainAndTicket('user7', 'company-b.com', 'T-007', $twoMonthsAgo);
        
        $this->entityManager->flush();
        
        // Hole die monatlichen Ticket-Statistiken nach Domain
        $statistics = $this->repository->getMonthlyTicketStatisticsByDomain();
        
        // Verifiziere, dass genau 6 Monate zurückgegeben werden
        $this->assertCount(6, $statistics);
        
        // Verifiziere, dass die Monate in absteigender Reihenfolge sind
        $previousMonth = null;
        foreach ($statistics as $stat) {
            $this->assertArrayHasKey('month', $stat);
            $this->assertArrayHasKey('domains', $stat);
            $this->assertArrayHasKey('total_tickets', $stat);
            
            if ($previousMonth !== null) {
                // Wir erwarten absteigende Reihenfolge (aktuellster Monat zuerst)
                $this->assertLessThan($previousMonth, $stat['month']);
            }
            $previousMonth = $stat['month'];
        }
        
        // Verifiziere spezifische Monatswerte
        $currentMonthKey = $now->format('Y-m');
        $foundCurrentMonth = false;
        
        foreach ($statistics as $stat) {
            if ($stat['month'] === $currentMonthKey) {
                // Sollte 2 Domains haben: company-a.com mit 2 Tickets, company-b.com mit 1 Ticket
                $this->assertArrayHasKey('company-a.com', $stat['domains']);
                $this->assertEquals(2, $stat['domains']['company-a.com']);
                $this->assertArrayHasKey('company-b.com', $stat['domains']);
                $this->assertEquals(1, $stat['domains']['company-b.com']);
                $this->assertEquals(3, $stat['total_tickets']); // Gesamt: 2 + 1 = 3
                $foundCurrentMonth = true;
            }
        }
        
        $this->assertTrue($foundCurrentMonth, 'Aktueller Monat sollte in den Statistiken vorhanden sein');
    }

    public function testGetMonthlyTicketStatisticsByDomainIncludesMonthsWithNoData(): void
    {
        // Erstelle nur Daten für den aktuellen Monat
        $now = new \DateTime();
        $this->createEmailSentWithDomainAndTicket('user1', 'test.com', 'T-001', $now);
        $this->entityManager->flush();
        
        $statistics = $this->repository->getMonthlyTicketStatisticsByDomain();
        
        // Alle 6 Monate sollten vorhanden sein, auch wenn keine Daten existieren
        $this->assertCount(6, $statistics);
        
        // Überprüfe, dass Monate ohne Daten leere Domain-Arrays haben
        $monthsWithEmptyDomains = 0;
        foreach ($statistics as $stat) {
            if (empty($stat['domains'])) {
                $monthsWithEmptyDomains++;
                $this->assertEquals(0, $stat['total_tickets']);
            }
        }
        
        $this->assertGreaterThanOrEqual(5, $monthsWithEmptyDomains, 'Mindestens 5 Monate sollten keine Domains haben');
    }

    public function testGetMonthlyTicketStatisticsByDomainOnlyCountsSuccessfulEmails(): void
    {
        $now = new \DateTime();
        
        // Erfolgreiche E-Mails
        $this->createEmailSentWithDomainAndTicket('user1', 'success.com', 'T-001', $now, 'sent');
        $this->createEmailSentWithDomainAndTicket('user2', 'success.com', 'T-002', $now, 'Versendet');
        
        // Fehlgeschlagene E-Mails - sollten nicht gezählt werden
        $this->createEmailSentWithDomainAndTicket('user3', 'error.com', 'T-003', $now, 'error: SMTP failed');
        $this->createEmailSentWithDomainAndTicket('user4', 'error.com', 'T-004', $now, 'Nicht versendet: Duplikat');
        
        $this->entityManager->flush();
        
        $statistics = $this->repository->getMonthlyTicketStatisticsByDomain();
        
        $currentMonthKey = $now->format('Y-m');
        $foundCurrentMonth = false;
        
        foreach ($statistics as $stat) {
            if ($stat['month'] === $currentMonthKey) {
                // Nur success.com sollte erscheinen, nicht error.com
                $this->assertArrayHasKey('success.com', $stat['domains']);
                $this->assertEquals(2, $stat['domains']['success.com']);
                $this->assertArrayNotHasKey('error.com', $stat['domains']);
                $this->assertEquals(2, $stat['total_tickets']);
                $foundCurrentMonth = true;
            }
        }
        
        $this->assertTrue($foundCurrentMonth, 'Aktueller Monat sollte in den Statistiken vorhanden sein');
    }

    public function testGetMonthlyTicketStatisticsByDomainCountsUniqueTicketsPerDomain(): void
    {
        $now = new \DateTime();
        
        // Mehrere Benutzer können das gleiche Ticket haben (sollte nur einmal pro Domain gezählt werden)
        $this->createEmailSentWithDomainAndTicket('user1', 'example.com', 'T-001', $now);
        $this->createEmailSentWithDomainAndTicket('user2', 'example.com', 'T-001', $now); // Duplikat Ticket
        $this->createEmailSentWithDomainAndTicket('user3', 'example.com', 'T-002', $now);
        
        // Gleiches Ticket in verschiedenen Domains sollte separat gezählt werden
        $this->createEmailSentWithDomainAndTicket('user4', 'other.com', 'T-001', $now);
        
        $this->entityManager->flush();
        
        $statistics = $this->repository->getMonthlyTicketStatisticsByDomain();
        
        $currentMonthKey = $now->format('Y-m');
        $foundCurrentMonth = false;
        
        foreach ($statistics as $stat) {
            if ($stat['month'] === $currentMonthKey) {
                // example.com sollte 2 eindeutige Tickets haben (T-001 und T-002)
                $this->assertArrayHasKey('example.com', $stat['domains']);
                $this->assertEquals(2, $stat['domains']['example.com']);
                
                // other.com sollte 1 Ticket haben (T-001)
                $this->assertArrayHasKey('other.com', $stat['domains']);
                $this->assertEquals(1, $stat['domains']['other.com']);
                
                $this->assertEquals(3, $stat['total_tickets']); // Gesamt: 2 + 1 = 3
                $foundCurrentMonth = true;
            }
        }
        
        $this->assertTrue($foundCurrentMonth, 'Aktueller Monat sollte in den Statistiken vorhanden sein');
    }

    public function testNormalizeDistinctValueHandlesValueObjects(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        $method = $reflection->getMethod('normalizeDistinctValue');
        $method->setAccessible(true);

        // plain string
        $this->assertEquals('abc', $method->invoke($this->repository, 'abc'));
        // int
        $this->assertEquals('42', $method->invoke($this->repository, 42));
        // object with getValue
        $obj = new class {
            public function getValue() { return 'VAL1'; }
        };
        $this->assertEquals('VAL1', $method->invoke($this->repository, $obj));
        // object with __toString
        $obj2 = new class {
            public function __toString() { return 'STR1'; }
        };
        $this->assertEquals('STR1', $method->invoke($this->repository, $obj2));
        // object without either
        $obj3 = new class {};
        $this->assertNull($method->invoke($this->repository, $obj3));
    }

    public function testGetMonthlyDomainCountsRawRespectsSinceParameter(): void
    {
        $now = new \DateTime();
        $this->createEmailSentWithDomain('user1', 'example.com', $now);
        $this->entityManager->flush();

        $future = new \DateTimeImmutable('+1 year');
        $statistics = $this->repository->getMonthlyDomainCountsRaw('username', $future);

        $this->assertCount(6, $statistics);
        foreach ($statistics as $stat) {
            $this->assertEmpty($stat['domains']);
            $this->assertEquals(0, $stat['total_users']);
        }
    }

    private function createEmailSentWithDomainAndTicket(
        string $username,
        string $domain,
        string $ticketId,
        \DateTime $timestamp,
        string $status = 'sent'
    ): EmailSent {
        $email = new EmailSent();
        $email->setTicketId(TicketId::fromString($ticketId));
        $email->setUsername(Username::fromString($username));
        $email->setEmail(EmailAddress::fromString($username . '@' . $domain));
        $email->setSubject('Test Subject');
        $email->setStatus(EmailStatus::fromString($status));
        $email->setTimestamp($timestamp);
        $email->setTestMode(false);
        
        $this->entityManager->persist($email);
        
        return $email;
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
