<?php

use PHPUnit\Framework\TestCase;
use App\Entity\AdminPassword;
use App\Exception\WeakPasswordException;

final class AdminPasswordTest extends TestCase
{
    public function testGetSetPasswordWithSecurePassword(): void
    {
        $entity = new AdminPassword();

        $this->assertNull($entity->getId());
        $this->assertNull($entity->getPassword());

        $entity->setPasswordFromPlaintext('StrongP@ssw0rd123!');
        $this->assertNotNull($entity->getPassword());
        $this->assertTrue($entity->verifyPassword('StrongP@ssw0rd123!'));
        $this->assertFalse($entity->verifyPassword('wrongpassword'));
    }

    public function testSetPasswordFromHash(): void
    {
        $entity = new AdminPassword();
        $hash = password_hash('TestP@ssw0rd123!', PASSWORD_BCRYPT);
        
        $entity->setPasswordFromHash($hash);
        $this->assertNotNull($entity->getPassword());
        $this->assertTrue($entity->verifyPassword('TestP@ssw0rd123!'));
    }

    public function testWeakPasswordRejection(): void
    {
        $this->expectException(WeakPasswordException::class);
        $entity = new AdminPassword();
        $entity->setPasswordFromPlaintext('weak');
    }

    public function testPasswordRehashNeeded(): void
    {
        $entity = new AdminPassword();
        $entity->setPasswordFromPlaintext('StrongP@ssw0rd123!');
        
        // This test just checks that the method exists and returns a boolean
        $this->assertIsBool($entity->needsPasswordRehash());
    }

    public function testVerifyPasswordWithNoPassword(): void
    {
        $entity = new AdminPassword();
        
        $this->assertFalse($entity->verifyPassword('anypassword'));
    }

    public function testNeedsRehashWithNoPassword(): void
    {
        $entity = new AdminPassword();
        
        $this->assertFalse($entity->needsPasswordRehash());
    }
}