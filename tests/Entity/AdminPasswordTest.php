<?php

use PHPUnit\Framework\TestCase;
use App\Entity\AdminPassword;

final class AdminPasswordTest extends TestCase
{
    public function testGetSetPlainAndEncryptedPassword(): void
    {
        $entity = new AdminPassword();

        $this->assertNull($entity->getId());
        $this->assertNull($entity->getPassword());
        $this->assertNull($entity->getPlainPassword());

        $chain = $entity->setPlainPassword('s3cr3t');
        $this->assertSame($entity, $chain);
        $this->assertSame('s3cr3t', $entity->getPlainPassword());

        $chain2 = $entity->setPassword('hashed');
        $this->assertSame($entity, $chain2);
        $this->assertSame('hashed', $entity->getPassword());
    }
}
