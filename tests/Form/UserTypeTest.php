<?php

namespace App\Tests\Form;

use App\Form\UserType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;

class UserTypeTest extends TestCase
{
    private $factory;

    protected function setUp(): void
    {
        $validator = Validation::createValidator();
        $this->factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension($validator))
            ->getFormFactory();
    }

    public function testFormHasExpectedFields(): void
    {
        $form = $this->factory->create(UserType::class);

        $this->assertTrue($form->has('username'));
        $this->assertTrue($form->has('email'));
        $this->assertTrue($form->has('excludedFromSurveys'));
    }
}
