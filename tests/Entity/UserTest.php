<?php

use PHPUnit\Framework\TestCase;
use App\Entity\User;
use App\ValueObject\EmailAddress;

final class UserTest extends TestCase
{
    public function testGetSetFieldsAndDefaults(): void
    {
        $u = new User();

        $this->assertNull($u->getId());
        $this->assertNull($u->getUsername());
        $this->assertNull($u->getEmail());
        $this->assertFalse($u->isExcludedFromSurveys());

        $u->setUsername('bob');
        $u->setEmail('bob@example.local');
        $u->setExcludedFromSurveys(true);

        $this->assertEquals(\App\ValueObject\Username::fromString('bob'), $u->getUsername());
        $this->assertEquals(EmailAddress::fromString('bob@example.local'), $u->getEmail());
        $this->assertTrue($u->isExcludedFromSurveys());
    }

    public function testSetEmailWithInvalidEmailRaisesException(): void
    {
        $this->expectException(\App\Exception\InvalidEmailAddressException::class);
        $u = new User();
        $u->setEmail('invalid-email');
    }

    // ========================================
    // 🏗️ DOMAIN LOGIC TESTS (DDD Rich Model)
    // ========================================

    /**
     * Testet die User Factory Method
     */
    public function testUserFactoryMethod(): void
    {
        $user = User::create('testuser', 'test@example.com');

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('testuser', $user->getUsername()->getValue());
        $this->assertEquals('test@example.com', $user->getEmail()->getValue());
        $this->assertFalse($user->isExcludedFromSurveys());
    }

    /**
     * Testet die Geschäftsregel für E-Mail-Berechtigung
     */
    public function testIsEligibleForEmailNotifications(): void
    {
        $user = User::create('testuser', 'test@example.com');

        // Standard: berechtigt
        $this->assertTrue($user->isEligibleForEmailNotifications());

        // Nach Ausschluss: nicht berechtigt
        $user->excludeFromSurveys();
        $this->assertFalse($user->isEligibleForEmailNotifications());

        // Nach Inklusion: wieder berechtigt
        $user->includeInSurveys();
        $this->assertTrue($user->isEligibleForEmailNotifications());
    }

    /**
     * Testet die Domain-Methoden für Survey-Ausschluss
     */
    public function testSurveyExclusionDomainLogic(): void
    {
        $user = User::create('testuser', 'test@example.com');

        $this->assertFalse($user->isExcludedFromSurveys());

        // Ausschließen
        $user->excludeFromSurveys('Ungewünschte E-Mails');
        $this->assertTrue($user->isExcludedFromSurveys());

        // Wieder einschließen
        $user->includeInSurveys();
        $this->assertFalse($user->isExcludedFromSurveys());
    }

    /**
     * Testet die updateEmail Domain-Methode
     */
    public function testUpdateEmailDomainLogic(): void
    {
        $user = User::create('testuser', 'old@example.com');

        // Gleiche E-Mail: keine Änderung
        $user->updateEmail('old@example.com');
        $this->assertEquals('old@example.com', $user->getEmail()->getValue());

        // Neue E-Mail: wird geändert
        $user->updateEmail('new@example.com');
        $this->assertEquals('new@example.com', $user->getEmail()->getValue());

        // Mit EmailAddress Value Object
        $emailObj = EmailAddress::fromString('newest@example.com');
        $user->updateEmail($emailObj);
        $this->assertEquals('newest@example.com', $user->getEmail()->getValue());
    }

    /**
     * Testet die updateUsername Domain-Methode
     */
    public function testUpdateUsernameDomainLogic(): void
    {
        $user = User::create('olduser', 'test@example.com');

        // Gleicher Username: keine Änderung
        $user->updateUsername('olduser');
        $this->assertEquals('olduser', $user->getUsername()->getValue());

        // Neuer Username: wird geändert
        $user->updateUsername('newuser');
        $this->assertEquals('newuser', $user->getUsername()->getValue());
    }

    /**
     * Testet die hasUsername Domain-Methode
     */
    public function testHasUsernameDomainLogic(): void
    {
        $user = User::create('TestUser', 'test@example.com');

        // Case-insensitive Vergleich (Username Value Object Logik)
        $this->assertTrue($user->hasUsername('testuser'));
        $this->assertTrue($user->hasUsername('TESTUSER'));
        $this->assertTrue($user->hasUsername('TestUser'));
        $this->assertFalse($user->hasUsername('otheruser'));
    }

    /**
     * Testet die hasEmail Domain-Methode
     */
    public function testHasEmailDomainLogic(): void
    {
        $user = User::create('testuser', 'test@example.com');

        $this->assertTrue($user->hasEmail('test@example.com'));
        $this->assertFalse($user->hasEmail('other@example.com'));

        // Mit EmailAddress Value Object
        $emailObj = EmailAddress::fromString('test@example.com');
        $this->assertTrue($user->hasEmail($emailObj));
    }

    /**
     * Testet die __toString Domain-Methode
     */
    public function testToStringRepresentation(): void
    {
        $user = User::create('testuser', 'test@example.com');

        $expected = 'User[testuser, test@example.com]';
        $this->assertEquals($expected, (string) $user);
    }
}
