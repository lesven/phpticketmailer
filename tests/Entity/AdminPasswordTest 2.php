<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\AdminPassword;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit-Tests für die AdminPassword-Entity
 * 
 * Diese Tests überprüfen die grundlegende Funktionalität der AdminPassword-Entity,
 * einschließlich Getter/Setter-Methoden, Validation-Constraints und Method-Chaining.
 */
#[CoversClass(AdminPassword::class)]
class AdminPasswordTest extends TestCase
{
    private AdminPassword $adminPassword;

    /**
     * Setup für jeden Test
     */
    protected function setUp(): void
    {
        $this->adminPassword = new AdminPassword();
    }

    /**
     * Testet die Initialisierung einer neuen AdminPassword-Instanz
     */
    public function testInitialization(): void
    {
        $adminPassword = new AdminPassword();
        
        $this->assertNull($adminPassword->getId());
        $this->assertNull($adminPassword->getPassword());
        $this->assertNull($adminPassword->getPlainPassword());
    }

    /**
     * Testet das Setzen und Abrufen der ID
     */
    public function testGetId(): void
    {
        // Da die ID von Doctrine generiert wird, sollte sie initial null sein
        $this->assertNull($this->adminPassword->getId());
    }

    /**
     * Testet das Setzen und Abrufen des verschlüsselten Passworts
     */
    public function testPasswordGetterAndSetter(): void
    {
        $encryptedPassword = '$2y$13$hashed.password.here';
        
        $result = $this->adminPassword->setPassword($encryptedPassword);
        
        // Prüft Method-Chaining
        $this->assertSame($this->adminPassword, $result);
        $this->assertSame($encryptedPassword, $this->adminPassword->getPassword());
    }

    /**
     * Testet das Setzen eines leeren verschlüsselten Passworts
     */
    public function testSetEmptyPassword(): void
    {
        $this->adminPassword->setPassword('');
        
        $this->assertSame('', $this->adminPassword->getPassword());
    }

    /**
     * Testet das Setzen und Abrufen des Plain-Passworts
     */
    public function testPlainPasswordGetterAndSetter(): void
    {
        $plainPassword = 'MySecurePassword123';
        
        $result = $this->adminPassword->setPlainPassword($plainPassword);
        
        // Prüft Method-Chaining
        $this->assertSame($this->adminPassword, $result);
        $this->assertSame($plainPassword, $this->adminPassword->getPlainPassword());
    }

    /**
     * Testet das Setzen eines null Plain-Passworts
     */
    public function testSetNullPlainPassword(): void
    {
        $this->adminPassword->setPlainPassword('initial');
        $this->adminPassword->setPlainPassword(null);
        
        $this->assertNull($this->adminPassword->getPlainPassword());
    }

    /**
     * Testet das Setzen eines leeren Plain-Passworts
     */
    public function testSetEmptyPlainPassword(): void
    {
        $this->adminPassword->setPlainPassword('');
        
        $this->assertSame('', $this->adminPassword->getPlainPassword());
    }

    /**
     * Testet das Method-Chaining für alle Setter-Methoden
     */
    public function testMethodChaining(): void
    {
        $result = $this->adminPassword
            ->setPassword('$2y$13$hashed.password')
            ->setPlainPassword('plainPassword123');
        
        $this->assertSame($this->adminPassword, $result);
        $this->assertSame('$2y$13$hashed.password', $this->adminPassword->getPassword());
        $this->assertSame('plainPassword123', $this->adminPassword->getPlainPassword());
    }

    /**
     * Testet die Unterscheidung zwischen verschlüsseltem und Plain-Passwort
     */
    public function testPasswordSeparation(): void
    {
        $plainPassword = 'MyPlainPassword';
        $encryptedPassword = '$2y$13$encrypted.version';
        
        $this->adminPassword
            ->setPlainPassword($plainPassword)
            ->setPassword($encryptedPassword);
        
        $this->assertSame($plainPassword, $this->adminPassword->getPlainPassword());
        $this->assertSame($encryptedPassword, $this->adminPassword->getPassword());
        $this->assertNotSame($plainPassword, $this->adminPassword->getPassword());
    }

    /**
     * Testet Unicode-Zeichen in Passwörtern
     */
    public function testUnicodePasswords(): void
    {
        $unicodePlainPassword = 'Päßwörd123üäö';
        $unicodeEncryptedPassword = '$2y$13$unicode.hashed.päßwörd';
        
        $this->adminPassword
            ->setPlainPassword($unicodePlainPassword)
            ->setPassword($unicodeEncryptedPassword);
        
        $this->assertSame($unicodePlainPassword, $this->adminPassword->getPlainPassword());
        $this->assertSame($unicodeEncryptedPassword, $this->adminPassword->getPassword());
    }

    /**
     * Testet sehr lange Passwörter
     */
    public function testLongPasswords(): void
    {
        $longPlainPassword = str_repeat('a', 1000);
        $longEncryptedPassword = '$2y$13$' . str_repeat('b', 200);
        
        $this->adminPassword
            ->setPlainPassword($longPlainPassword)
            ->setPassword($longEncryptedPassword);
        
        $this->assertSame($longPlainPassword, $this->adminPassword->getPlainPassword());
        $this->assertSame($longEncryptedPassword, $this->adminPassword->getPassword());
        $this->assertSame(1000, strlen($this->adminPassword->getPlainPassword()));
    }

    /**
     * Testet Sonderzeichen in Passwörtern
     */
    public function testSpecialCharactersInPasswords(): void
    {
        $specialPassword = '!@#$%^&*()_+-=[]{}|;:,.<>?`~"\'\\';
        $specialEncrypted = '$2y$13$special.chars.encrypted';
        
        $this->adminPassword
            ->setPlainPassword($specialPassword)
            ->setPassword($specialEncrypted);
        
        $this->assertSame($specialPassword, $this->adminPassword->getPlainPassword());
        $this->assertSame($specialEncrypted, $this->adminPassword->getPassword());
    }

    /**
     * Testet das Überschreiben von bereits gesetzten Werten
     */
    public function testOverwritingValues(): void
    {
        // Erste Werte setzen
        $this->adminPassword
            ->setPlainPassword('firstPlain')
            ->setPassword('firstEncrypted');
        
        $this->assertSame('firstPlain', $this->adminPassword->getPlainPassword());
        $this->assertSame('firstEncrypted', $this->adminPassword->getPassword());
        
        // Werte überschreiben
        $this->adminPassword
            ->setPlainPassword('secondPlain')
            ->setPassword('secondEncrypted');
        
        $this->assertSame('secondPlain', $this->adminPassword->getPlainPassword());
        $this->assertSame('secondEncrypted', $this->adminPassword->getPassword());
    }

    /**
     * Data Provider für verschiedene Passwort-Kombinationen
     */
    public static function passwordDataProvider(): array
    {
        return [
            'standard_password' => ['password123', '$2y$13$hashed123'],
            'empty_strings' => ['', ''],
            'numeric_password' => ['123456789', '$2y$13$numeric'],
            'special_chars' => ['!@#$%^&*()', '$2y$13$special'],
            'unicode' => ['üäöß', '$2y$13$unicode'],
            'spaces' => ['pass word', '$2y$13$with.spaces'],
        ];
    }

    /**
     * Testet verschiedene Passwort-Kombinationen mit Data Provider
     */
    #[DataProvider('passwordDataProvider')]
    public function testPasswordCombinations(string $plainPassword, string $encryptedPassword): void
    {
        $this->adminPassword
            ->setPlainPassword($plainPassword)
            ->setPassword($encryptedPassword);
        
        $this->assertSame($plainPassword, $this->adminPassword->getPlainPassword());
        $this->assertSame($encryptedPassword, $this->adminPassword->getPassword());
    }
}
