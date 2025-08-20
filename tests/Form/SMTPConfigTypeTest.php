<?php

namespace App\Tests\Form;

use App\Form\SMTPConfigType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;

class SMTPConfigTypeTest extends TestCase
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
        $form = $this->factory->create(SMTPConfigType::class);

        $expected = ['host','port','username','password','useTLS','verifySSL','senderEmail','senderName','ticketBaseUrl'];
        foreach ($expected as $field) {
            $this->assertTrue($form->has($field), "Field $field missing");
        }
    }
}
