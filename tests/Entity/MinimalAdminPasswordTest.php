<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\AdminPassword;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AdminPassword::class)]
class MinimalAdminPasswordTest extends TestCase
{
    public function testBasic(): void
    {
        $adminPassword = new AdminPassword();
        $adminPassword->setPassword('test123');
        
        $this->assertSame('test123', $adminPassword->getPassword());
    }
}
