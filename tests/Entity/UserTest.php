<?php

use PHPUnit\Framework\TestCase;
use App\Entity\User;

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
        $this->assertSame('bob@example.local', $u->getEmail());
        $this->assertTrue($u->isExcludedFromSurveys());
    }

    public function testSetEmailNullRaisesTypeError(): void
    {
        $this->expectException(\TypeError::class);
        $u = new User();
        /** @phpstan-ignore-next-line */
        $u->setEmail(null);
    }
}
