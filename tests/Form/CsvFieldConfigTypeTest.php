<?php

namespace App\Tests\Form;

use App\Form\CsvFieldConfigType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;

class CsvFieldConfigTypeTest extends TestCase
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
        $form = $this->factory->create(CsvFieldConfigType::class);

        $this->assertTrue($form->has('ticketIdField'));
        $this->assertTrue($form->has('usernameField'));
        $this->assertTrue($form->has('ticketNameField'));
        $this->assertTrue($form->has('createdField'));
    }

    public function testMaxLengthConstraints(): void
    {
        $form = $this->factory->create(CsvFieldConfigType::class);

        $data = [
            'ticketIdField' => str_repeat('a', 60),
            'usernameField' => str_repeat('b', 60),
            'ticketNameField' => str_repeat('c', 60),
            'createdField' => str_repeat('d', 60),
        ];

        $form->submit($data);
        $this->assertFalse($form->isValid());

        $errors = [];
        foreach ($form as $child) {
            foreach ($child->getErrors(true) as $err) {
                $errors[] = $err->getMessage();
            }
        }

        $this->assertNotEmpty($errors, 'Expected validation errors for too long values');
    }
}
