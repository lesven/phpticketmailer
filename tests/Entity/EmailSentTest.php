<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\EmailSent;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit-Tests für die EmailSent-Entity
 * 
 * Diese Tests überprüfen die Funktionalität der EmailSent-Entity,
 * einschließlich aller Properties, Timestamp-Formatierung, Testmodus-Logik
 * und verschiedener Edge Cases.
 */
#[CoversClass(EmailSent::class)]
class EmailSentTest extends TestCase
{
    private EmailSent $emailSent;

    /**
     * Setup für jeden Test
     */
    protected function setUp(): void
    {
        $this->emailSent = new EmailSent();
    }

    /**
     * Testet die Initialisierung einer neuen EmailSent-Instanz
     */
    public function testInitialization(): void
    {
        $emailSent = new EmailSent();
        
        $this->assertNull($emailSent->getId());
        $this->assertNull($emailSent->getTicketId());
        $this->assertNull($emailSent->getUsername());
        $this->assertNull($emailSent->getEmail());
        $this->assertNull($emailSent->getSubject());
        $this->assertNull($emailSent->getStatus());
        $this->assertNull($emailSent->getTimestamp());
        $this->assertNull($emailSent->getTestMode());
        $this->assertNull($emailSent->getTicketName());
    }

    /**
     * Testet das Setzen und Abrufen der Ticket-ID
     */
    public function testTicketIdGetterAndSetter(): void
    {
        $ticketId = 'TICKET-12345';
        
        $result = $this->emailSent->setTicketId($ticketId);
        
        $this->assertSame($this->emailSent, $result);
        $this->assertSame($ticketId, $this->emailSent->getTicketId());
    }

    /**
     * Testet das Setzen und Abrufen des Benutzernamens
     */
    public function testUsernameGetterAndSetter(): void
    {
        $username = 'john.doe';
        
        $result = $this->emailSent->setUsername($username);
        
        $this->assertSame($this->emailSent, $result);
        $this->assertSame($username, $this->emailSent->getUsername());
    }

    /**
     * Testet das Setzen und Abrufen der E-Mail-Adresse
     */
    public function testEmailGetterAndSetter(): void
    {
        $email = 'john.doe@example.com';
        
        $result = $this->emailSent->setEmail($email);
        
        $this->assertSame($this->emailSent, $result);
        $this->assertSame($email, $this->emailSent->getEmail());
    }

    /**
     * Testet das Setzen und Abrufen des Betreffs
     */
    public function testSubjectGetterAndSetter(): void
    {
        $subject = 'Ticket-Umfrage: Bitte bewerten Sie unseren Service';
        
        $result = $this->emailSent->setSubject($subject);
        
        $this->assertSame($this->emailSent, $result);
        $this->assertSame($subject, $this->emailSent->getSubject());
    }

    /**
     * Testet das Setzen und Abrufen des Status
     */
    public function testStatusGetterAndSetter(): void
    {
        $status = 'sent';
        
        $result = $this->emailSent->setStatus($status);
        
        $this->assertSame($this->emailSent, $result);
        $this->assertSame($status, $this->emailSent->getStatus());
    }

    /**
     * Testet das Setzen und Abrufen des Timestamps
     */
    public function testTimestampGetterAndSetter(): void
    {
        $timestamp = new \DateTime('2025-08-16 10:30:45');
        
        $result = $this->emailSent->setTimestamp($timestamp);
        
        $this->assertSame($this->emailSent, $result);
        $this->assertSame($timestamp, $this->emailSent->getTimestamp());
    }

    /**
     * Testet das Setzen und Abrufen des Testmodus
     */
    public function testTestModeGetterAndSetter(): void
    {
        $result = $this->emailSent->setTestMode(true);
        
        $this->assertSame($this->emailSent, $result);
        $this->assertTrue($this->emailSent->getTestMode());
        
        $this->emailSent->setTestMode(false);
        $this->assertFalse($this->emailSent->getTestMode());
    }

    /**
     * Testet das Setzen und Abrufen des Ticket-Namens
     */
    public function testTicketNameGetterAndSetter(): void
    {
        $ticketName = 'Support-Anfrage Netzwerkproblem';
        
        $result = $this->emailSent->setTicketName($ticketName);
        
        $this->assertSame($this->emailSent, $result);
        $this->assertSame($ticketName, $this->emailSent->getTicketName());
    }

    /**
     * Testet das Setzen von null für den Ticket-Namen
     */
    public function testSetNullTicketName(): void
    {
        $this->emailSent->setTicketName('initial');
        $this->emailSent->setTicketName(null);
        
        $this->assertNull($this->emailSent->getTicketName());
    }

    /**
     * Testet die Formatierung des Timestamps
     */
    public function testFormattedTimestamp(): void
    {
        $timestamp = new \DateTime('2025-08-16 14:25:33');
        $this->emailSent->setTimestamp($timestamp);
        
        $formattedTimestamp = $this->emailSent->getFormattedTimestamp();
        
        $this->assertSame('2025-08-16 14:25:33', $formattedTimestamp);
    }

    /**
     * Testet die Formatierung des Timestamps bei null
     */
    public function testFormattedTimestampWithNull(): void
    {
        $this->assertNull($this->emailSent->getTimestamp());
        
        $formattedTimestamp = $this->emailSent->getFormattedTimestamp();
        
        $this->assertSame('', $formattedTimestamp);
    }

    /**
     * Testet das Method-Chaining für alle Setter-Methoden
     */
    public function testMethodChaining(): void
    {
        $timestamp = new \DateTime('2025-08-16 12:00:00');
        
        $result = $this->emailSent
            ->setTicketId('TICKET-123')
            ->setUsername('testuser')
            ->setEmail('test@example.com')
            ->setSubject('Test Subject')
            ->setStatus('sent')
            ->setTimestamp($timestamp)
            ->setTestMode(true)
            ->setTicketName('Test Ticket');
        
        $this->assertSame($this->emailSent, $result);
        $this->assertSame('TICKET-123', $this->emailSent->getTicketId());
        $this->assertSame('testuser', $this->emailSent->getUsername());
        $this->assertSame('test@example.com', $this->emailSent->getEmail());
        $this->assertSame('Test Subject', $this->emailSent->getSubject());
        $this->assertSame('sent', $this->emailSent->getStatus());
        $this->assertSame($timestamp, $this->emailSent->getTimestamp());
        $this->assertTrue($this->emailSent->getTestMode());
        $this->assertSame('Test Ticket', $this->emailSent->getTicketName());
    }

    /**
     * Testet verschiedene Status-Werte
     */
    public function testDifferentStatusValues(): void
    {
        $statusValues = ['sent', 'error: SMTP connection failed', 'pending', 'cancelled'];
        
        foreach ($statusValues as $status) {
            $this->emailSent->setStatus($status);
            $this->assertSame($status, $this->emailSent->getStatus());
        }
    }

    /**
     * Testet Unicode-Zeichen in Text-Feldern
     */
    public function testUnicodeCharacters(): void
    {
        $this->emailSent
            ->setTicketId('TICKET-ÄÖÜ-123')
            ->setUsername('müller.äöü')
            ->setEmail('müller@example.de')
            ->setSubject('Umfrage: Wie zufrieden sind Sie?')
            ->setTicketName('Störung Netzwerk üäö');
        
        $this->assertSame('TICKET-ÄÖÜ-123', $this->emailSent->getTicketId());
        $this->assertSame('müller.äöü', $this->emailSent->getUsername());
        $this->assertSame('müller@example.de', $this->emailSent->getEmail());
        $this->assertSame('Umfrage: Wie zufrieden sind Sie?', $this->emailSent->getSubject());
        $this->assertSame('Störung Netzwerk üäö', $this->emailSent->getTicketName());
    }

    /**
     * Testet sehr lange Text-Werte
     */
    public function testLongTextValues(): void
    {
        $longTicketId = str_repeat('TICKET-', 36) . '123'; // ~250 Zeichen
        $longUsername = str_repeat('user', 60) . '123'; // ~250 Zeichen
        $longEmail = str_repeat('test', 60) . '@example.com'; // ~250 Zeichen
        $longSubject = str_repeat('Subject ', 30) . 'End'; // ~250 Zeichen
        $longTicketName = str_repeat('Ticket ', 35) . 'Name'; // ~250 Zeichen
        
        $this->emailSent
            ->setTicketId($longTicketId)
            ->setUsername($longUsername)
            ->setEmail($longEmail)
            ->setSubject($longSubject)
            ->setTicketName($longTicketName);
        
        $this->assertSame($longTicketId, $this->emailSent->getTicketId());
        $this->assertSame($longUsername, $this->emailSent->getUsername());
        $this->assertSame($longEmail, $this->emailSent->getEmail());
        $this->assertSame($longSubject, $this->emailSent->getSubject());
        $this->assertSame($longTicketName, $this->emailSent->getTicketName());
    }

    /**
     * Testet verschiedene Timestamp-Formate
     */
    public function testDifferentTimestampFormats(): void
    {
        $testCases = [
            new \DateTime('2025-01-01 00:00:00'),
            new \DateTime('2025-12-31 23:59:59'),
            new \DateTime('2025-08-16 12:30:45'),
            new \DateTimeImmutable('2025-08-16 15:45:30'),
        ];
        
        foreach ($testCases as $timestamp) {
            $this->emailSent->setTimestamp($timestamp);
            $this->assertSame($timestamp, $this->emailSent->getTimestamp());
            $this->assertSame($timestamp->format('Y-m-d H:i:s'), $this->emailSent->getFormattedTimestamp());
        }
    }

    /**
     * Data Provider für E-Mail-Adressen
     */
    public static function emailDataProvider(): array
    {
        return [
            'standard_email' => ['user@example.com'],
            'subdomain_email' => ['user@mail.example.com'],
            'plus_addressing' => ['user+tag@example.com'],
            'dots_in_local' => ['user.name@example.com'],
            'international_domain' => ['user@example.de'],
            'numbers_in_local' => ['user123@example.com'],
        ];
    }

    /**
     * Testet verschiedene E-Mail-Formate mit Data Provider
     */
    #[DataProvider('emailDataProvider')]
    public function testEmailFormats(string $email): void
    {
        $this->emailSent->setEmail($email);
        $this->assertSame($email, $this->emailSent->getEmail());
    }

    /**
     * Data Provider für Status-Werte
     */
    public static function statusDataProvider(): array
    {
        return [
            'sent' => ['sent'],
            'pending' => ['pending'],
            'error_smtp' => ['error: SMTP connection failed'],
            'error_invalid_email' => ['error: Invalid email address'],
            'cancelled' => ['cancelled'],
            'retry' => ['retry'],
        ];
    }

    /**
     * Testet verschiedene Status-Werte mit Data Provider
     */
    #[DataProvider('statusDataProvider')]
    public function testStatusValues(string $status): void
    {
        $this->emailSent->setStatus($status);
        $this->assertSame($status, $this->emailSent->getStatus());
    }

    /**
     * Testet leere String-Werte
     */
    public function testEmptyStringValues(): void
    {
        $this->emailSent
            ->setTicketId('')
            ->setUsername('')
            ->setEmail('')
            ->setSubject('')
            ->setStatus('')
            ->setTicketName('');
        
        $this->assertSame('', $this->emailSent->getTicketId());
        $this->assertSame('', $this->emailSent->getUsername());
        $this->assertSame('', $this->emailSent->getEmail());
        $this->assertSame('', $this->emailSent->getSubject());
        $this->assertSame('', $this->emailSent->getStatus());
        $this->assertSame('', $this->emailSent->getTicketName());
    }

    /**
     * Testet die Zeitzone-Behandlung
     */
    public function testTimezoneHandling(): void
    {
        $utcTimestamp = new \DateTime('2025-08-16 12:00:00', new \DateTimeZone('UTC'));
        $berlinTimestamp = new \DateTime('2025-08-16 14:00:00', new \DateTimeZone('Europe/Berlin'));
        
        $this->emailSent->setTimestamp($utcTimestamp);
        $this->assertSame('2025-08-16 12:00:00', $this->emailSent->getFormattedTimestamp());
        
        $this->emailSent->setTimestamp($berlinTimestamp);
        $this->assertSame('2025-08-16 14:00:00', $this->emailSent->getFormattedTimestamp());
    }
}
