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

        $this->assertSame('bob', $u->getUsername());
        $this->assertEquals(EmailAddress::fromString('bob@example.local'), $u->getEmail());
        $this->assertTrue($u->isExcludedFromSurveys());
    }

    public function testSetEmailWithInvalidEmailRaisesException(): void
    {
        $this->expectException(\App\Exception\InvalidEmailAddressException::class);
        $u = new User();
        $u->setEmail('invalid-email');
    }
}
