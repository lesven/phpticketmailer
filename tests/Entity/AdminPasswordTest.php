<?php

namespace App\Tests\Entity;

use App\Entity\AdminPassword;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class AdminPasswordTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $adminPassword = new AdminPassword();

        $adminPassword->setPassword('hashed');
        $adminPassword->setPlainPassword('plain1234');

        $this->assertSame('hashed', $adminPassword->getPassword());
        $this->assertSame('plain1234', $adminPassword->getPlainPassword());
    }

    public function testPlainPasswordCannotBeBlank(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $adminPassword = new AdminPassword();
        $adminPassword->setPlainPassword('');

        $violations = $validator->validate($adminPassword);

        $this->assertGreaterThan(0, $violations->count());
        $this->assertSame('Das Passwort darf nicht leer sein', $violations[0]->getMessage());
    }

    public function testPlainPasswordMustHaveMinimumLength(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $adminPassword = new AdminPassword();
        $adminPassword->setPlainPassword('short');

        $violations = $validator->validate($adminPassword);

        $this->assertGreaterThan(0, $violations->count());
        $this->assertSame('Das Passwort muss mindestens 8 Zeichen enthalten', $violations[0]->getMessage());
    }

    public function testPlainPasswordValid(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $adminPassword = new AdminPassword();
        $adminPassword->setPlainPassword('longenough');

        $violations = $validator->validate($adminPassword);

        $this->assertCount(0, $violations);
    }
}
