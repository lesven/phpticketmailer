<?php

namespace App\Tests\Form;

use App\Form\UserImportType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;

class UserImportTypeTest extends TestCase
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
        $form = $this->factory->create(UserImportType::class);

        $this->assertTrue($form->has('csvFile'));
        $this->assertTrue($form->has('clearExisting'));
    }

    public function testCsvFileIsRequired(): void
    {
        $form = $this->factory->create(UserImportType::class);
        $this->assertTrue($form->get('csvFile')->getConfig()->getRequired());
    }
}
