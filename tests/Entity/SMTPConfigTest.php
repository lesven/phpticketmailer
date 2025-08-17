<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\SMTPConfig;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit-Tests für die SMTPConfig-Entity
 * 
 * Diese Tests überprüfen die Funktionalität der SMTPConfig-Entity,
 * einschließlich SMTP-Einstellungen, DSN-Generierung, TLS-Konfiguration
 * und verschiedener Edge Cases.
 */
#[CoversClass(SMTPConfig::class)]
class SMTPConfigTest extends TestCase
{
    private SMTPConfig $smtpConfig;

    /**
     * Setup für jeden Test
     */
    protected function setUp(): void
    {
        $this->smtpConfig = new SMTPConfig();
    }

    /**
     * Testet die Initialisierung einer neuen SMTPConfig-Instanz
     */
    public function testInitialization(): void
    {
        $smtpConfig = new SMTPConfig();
        
        $this->assertNull($smtpConfig->getId());
        $this->assertNull($smtpConfig->getHost());
        $this->assertNull($smtpConfig->getPort());
        $this->assertNull($smtpConfig->getUsername());
        $this->assertNull($smtpConfig->getPassword());
        $this->assertFalse($smtpConfig->isUseTLS());
        $this->assertNull($smtpConfig->getSenderEmail());
        $this->assertNull($smtpConfig->getSenderName());
    }

    /**
     * Testet das Setzen und Abrufen des Hosts
     */
    public function testHostGetterAndSetter(): void
    {
        $host = 'smtp.example.com';
        
        $result = $this->smtpConfig->setHost($host);
        
        $this->assertSame($this->smtpConfig, $result);
        $this->assertSame($host, $this->smtpConfig->getHost());
    }

    /**
     * Testet das Setzen und Abrufen des Ports
     */
    public function testPortGetterAndSetter(): void
    {
        $port = 587;
        
        $result = $this->smtpConfig->setPort($port);
        
        $this->assertSame($this->smtpConfig, $result);
        $this->assertSame($port, $this->smtpConfig->getPort());
    }

    /**
     * Testet das Setzen und Abrufen des Benutzernamens
     */
    public function testUsernameGetterAndSetter(): void
    {
        $username = 'smtp.user@example.com';
        
        $result = $this->smtpConfig->setUsername($username);
        
        $this->assertSame($this->smtpConfig, $result);
        $this->assertSame($username, $this->smtpConfig->getUsername());
    }

    /**
     * Testet das Setzen von null für den Benutzernamen
     */
    public function testSetNullUsername(): void
    {
        $this->smtpConfig->setUsername('initial');
        $this->smtpConfig->setUsername(null);
        
        $this->assertNull($this->smtpConfig->getUsername());
    }

    /**
     * Testet das Setzen und Abrufen des Passworts
     */
    public function testPasswordGetterAndSetter(): void
    {
        $password = 'secure_password_123';
        
        $result = $this->smtpConfig->setPassword($password);
        
        $this->assertSame($this->smtpConfig, $result);
        $this->assertSame($password, $this->smtpConfig->getPassword());
    }

    /**
     * Testet das Setzen von null für das Passwort
     */
    public function testSetNullPassword(): void
    {
        $this->smtpConfig->setPassword('initial');
        $this->smtpConfig->setPassword(null);
        
        $this->assertNull($this->smtpConfig->getPassword());
    }

    /**
     * Testet das Setzen und Abrufen der TLS-Einstellung
     */
    public function testUseTLSGetterAndSetter(): void
    {
        $result = $this->smtpConfig->setUseTLS(true);
        
        $this->assertSame($this->smtpConfig, $result);
        $this->assertTrue($this->smtpConfig->isUseTLS());
        
        $this->smtpConfig->setUseTLS(false);
        $this->assertFalse($this->smtpConfig->isUseTLS());
    }

    /**
     * Testet das Setzen und Abrufen der Absender-E-Mail
     */
    public function testSenderEmailGetterAndSetter(): void
    {
        $senderEmail = 'sender@example.com';
        
        $result = $this->smtpConfig->setSenderEmail($senderEmail);
        
        $this->assertSame($this->smtpConfig, $result);
        $this->assertSame($senderEmail, $this->smtpConfig->getSenderEmail());
    }

    /**
     * Testet das Setzen und Abrufen des Absender-Namens
     */
    public function testSenderNameGetterAndSetter(): void
    {
        $senderName = 'Support Team';
        
        $result = $this->smtpConfig->setSenderName($senderName);
        
        $this->assertSame($this->smtpConfig, $result);
        $this->assertSame($senderName, $this->smtpConfig->getSenderName());
    }

    /**
     * Testet die DSN-Generierung ohne Authentifizierung
     */
    public function testGetDSNWithoutAuth(): void
    {
        $this->smtpConfig
            ->setHost('smtp.example.com')
            ->setPort(25)
            ->setUseTLS(false);
        
        $expectedDSN = 'smtp://smtp.example.com:25';
        
        $this->assertSame($expectedDSN, $this->smtpConfig->getDSN());
    }

    /**
     * Testet die DSN-Generierung mit Authentifizierung
     */
    public function testGetDSNWithAuth(): void
    {
        $this->smtpConfig
            ->setHost('smtp.gmail.com')
            ->setPort(587)
            ->setUsername('user@gmail.com')
            ->setPassword('app_password')
            ->setUseTLS(false);
        
        $expectedDSN = 'smtp://user%40gmail.com:app_password@smtp.gmail.com:587';
        
        $this->assertSame($expectedDSN, $this->smtpConfig->getDSN());
    }

    /**
     * Testet die DSN-Generierung mit TLS
     */
    public function testGetDSNWithTLS(): void
    {
        $this->smtpConfig
            ->setHost('smtp.example.com')
            ->setPort(465)
            ->setUseTLS(true);
        
        $expectedDSN = 'smtp://smtp.example.com:465?encryption=tls';
        
        $this->assertSame($expectedDSN, $this->smtpConfig->getDSN());
    }

    /**
     * Testet die DSN-Generierung mit Authentifizierung und TLS
     */
    public function testGetDSNWithAuthAndTLS(): void
    {
        $this->smtpConfig
            ->setHost('smtp.office365.com')
            ->setPort(587)
            ->setUsername('user@company.com')
            ->setPassword('secure123')
            ->setUseTLS(true);
        
        $expectedDSN = 'smtp://user%40company.com:secure123@smtp.office365.com:587?encryption=tls';
        
        $this->assertSame($expectedDSN, $this->smtpConfig->getDSN());
    }

    /**
     * Testet die DSN-Generierung mit Sonderzeichen in Credentials
     */
    public function testGetDSNWithSpecialCharacters(): void
    {
        $this->smtpConfig
            ->setHost('smtp.example.com')
            ->setPort(587)
            ->setUsername('user+tag@example.com')
            ->setPassword('pass@word:123')
            ->setUseTLS(false);
        
        $expectedDSN = 'smtp://user%2Btag%40example.com:pass%40word%3A123@smtp.example.com:587';
        
        $this->assertSame($expectedDSN, $this->smtpConfig->getDSN());
    }

    /**
     * Testet die DSN-Generierung mit nur Username (ohne Passwort)
     */
    public function testGetDSNWithUsernameOnly(): void
    {
        $this->smtpConfig
            ->setHost('smtp.example.com')
            ->setPort(25)
            ->setUsername('user@example.com')
            ->setPassword(null);
        
        $expectedDSN = 'smtp://smtp.example.com:25';
        
        $this->assertSame($expectedDSN, $this->smtpConfig->getDSN());
    }

    /**
     * Testet die DSN-Generierung mit nur Passwort (ohne Username)
     */
    public function testGetDSNWithPasswordOnly(): void
    {
        $this->smtpConfig
            ->setHost('smtp.example.com')
            ->setPort(25)
            ->setUsername(null)
            ->setPassword('password123');
        
        $expectedDSN = 'smtp://smtp.example.com:25';
        
        $this->assertSame($expectedDSN, $this->smtpConfig->getDSN());
    }

    /**
     * Testet das Method-Chaining für alle Setter-Methoden
     */
    public function testMethodChaining(): void
    {
        $result = $this->smtpConfig
            ->setHost('smtp.example.com')
            ->setPort(587)
            ->setUsername('user@example.com')
            ->setPassword('password')
            ->setUseTLS(true)
            ->setSenderEmail('sender@example.com')
            ->setSenderName('Test Sender');
        
        $this->assertSame($this->smtpConfig, $result);
        $this->assertSame('smtp.example.com', $this->smtpConfig->getHost());
        $this->assertSame(587, $this->smtpConfig->getPort());
        $this->assertSame('user@example.com', $this->smtpConfig->getUsername());
        $this->assertSame('password', $this->smtpConfig->getPassword());
        $this->assertTrue($this->smtpConfig->isUseTLS());
        $this->assertSame('sender@example.com', $this->smtpConfig->getSenderEmail());
        $this->assertSame('Test Sender', $this->smtpConfig->getSenderName());
    }

    /**
     * Data Provider für verschiedene SMTP-Ports
     */
    public static function portDataProvider(): array
    {
        return [
            'smtp_standard' => [25],
            'smtp_submission' => [587],
            'smtp_ssl' => [465],
            'custom_port' => [2525],
            'alternative_port' => [1025],
        ];
    }

    /**
     * Testet verschiedene SMTP-Ports mit Data Provider
     */
    #[DataProvider('portDataProvider')]
    public function testPortValues(int $port): void
    {
        $this->smtpConfig->setPort($port);
        $this->assertSame($port, $this->smtpConfig->getPort());
    }

    /**
     * Data Provider für verschiedene SMTP-Hosts
     */
    public static function hostDataProvider(): array
    {
        return [
            'gmail' => ['smtp.gmail.com'],
            'outlook' => ['smtp.office365.com'],
            'yahoo' => ['smtp.mail.yahoo.com'],
            'custom_domain' => ['mail.example.com'],
            'ip_address' => ['192.168.1.100'],
            'localhost' => ['localhost'],
            'subdomain' => ['smtp.mail.company.com'],
        ];
    }

    /**
     * Testet verschiedene SMTP-Hosts mit Data Provider
     */
    #[DataProvider('hostDataProvider')]
    public function testHostValues(string $host): void
    {
        $this->smtpConfig->setHost($host);
        $this->assertSame($host, $this->smtpConfig->getHost());
    }

    /**
     * Testet Unicode-Zeichen in Text-Feldern
     */
    public function testUnicodeCharacters(): void
    {
        $this->smtpConfig
            ->setHost('smtp.ümlaut.de')
            ->setUsername('müller@ümlaut.de')
            ->setPassword('päßwörd123')
            ->setSenderEmail('absender@ümlaut.de')
            ->setSenderName('Müller & Söhne GmbH');
        
        $this->assertSame('smtp.ümlaut.de', $this->smtpConfig->getHost());
        $this->assertSame('müller@ümlaut.de', $this->smtpConfig->getUsername());
        $this->assertSame('päßwörd123', $this->smtpConfig->getPassword());
        $this->assertSame('absender@ümlaut.de', $this->smtpConfig->getSenderEmail());
        $this->assertSame('Müller & Söhne GmbH', $this->smtpConfig->getSenderName());
    }

    /**
     * Testet leere String-Werte
     */
    public function testEmptyStringValues(): void
    {
        $this->smtpConfig
            ->setHost('')
            ->setUsername('')
            ->setPassword('')
            ->setSenderEmail('')
            ->setSenderName('');
        
        $this->assertSame('', $this->smtpConfig->getHost());
        $this->assertSame('', $this->smtpConfig->getUsername());
        $this->assertSame('', $this->smtpConfig->getPassword());
        $this->assertSame('', $this->smtpConfig->getSenderEmail());
        $this->assertSame('', $this->smtpConfig->getSenderName());
    }

    /**
     * Testet sehr lange String-Werte
     */
    public function testLongStringValues(): void
    {
        $longHost = str_repeat('sub.', 60) . 'example.com'; // ~250 Zeichen
        $longUsername = str_repeat('user', 60) . '@example.com'; // ~250 Zeichen
        $longPassword = str_repeat('pass', 60) . '123'; // ~250 Zeichen
        $longSenderEmail = str_repeat('sender', 40) . '@example.com'; // ~250 Zeichen
        $longSenderName = str_repeat('Company ', 30) . 'Ltd'; // ~250 Zeichen
        
        $this->smtpConfig
            ->setHost($longHost)
            ->setUsername($longUsername)
            ->setPassword($longPassword)
            ->setSenderEmail($longSenderEmail)
            ->setSenderName($longSenderName);
        
        $this->assertSame($longHost, $this->smtpConfig->getHost());
        $this->assertSame($longUsername, $this->smtpConfig->getUsername());
        $this->assertSame($longPassword, $this->smtpConfig->getPassword());
        $this->assertSame($longSenderEmail, $this->smtpConfig->getSenderEmail());
        $this->assertSame($longSenderName, $this->smtpConfig->getSenderName());
    }

    /**
     * Testet Grenzwerte für Port-Nummern
     */
    public function testPortBoundaryValues(): void
    {
        $boundaryPorts = [1, 25, 465, 587, 2525, 65535];
        
        foreach ($boundaryPorts as $port) {
            $this->smtpConfig->setPort($port);
            $this->assertSame($port, $this->smtpConfig->getPort());
        }
    }

    /**
     * Testet die komplette SMTP-Konfiguration für reale Anbieter
     */
    public function testRealWorldConfigurations(): void
    {
        // Gmail-Konfiguration
        $this->smtpConfig
            ->setHost('smtp.gmail.com')
            ->setPort(587)
            ->setUsername('user@gmail.com')
            ->setPassword('app_password')
            ->setUseTLS(true)
            ->setSenderEmail('user@gmail.com')
            ->setSenderName('User Name');
        
        $expectedGmailDSN = 'smtp://user%40gmail.com:app_password@smtp.gmail.com:587?encryption=tls';
        $this->assertSame($expectedGmailDSN, $this->smtpConfig->getDSN());
        
        // Office365-Konfiguration
        $this->smtpConfig
            ->setHost('smtp.office365.com')
            ->setPort(587)
            ->setUsername('user@company.com')
            ->setPassword('secure_pass')
            ->setUseTLS(true)
            ->setSenderEmail('user@company.com')
            ->setSenderName('Company User');
        
        $expectedO365DSN = 'smtp://user%40company.com:secure_pass@smtp.office365.com:587?encryption=tls';
        $this->assertSame($expectedO365DSN, $this->smtpConfig->getDSN());
    }
}
