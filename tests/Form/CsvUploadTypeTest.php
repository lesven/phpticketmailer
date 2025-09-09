<?php

namespace App\Tests\Form;

use App\Form\CsvUploadType;
use App\Form\CsvFieldConfigType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;

class CsvUploadTypeTest extends TestCase
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
        $form = $this->factory->create(CsvUploadType::class);

        $this->assertTrue($form->has('csvFile'));
        $this->assertTrue($form->has('csvFieldConfig'));
        $this->assertTrue($form->has('testMode'));
        $this->assertTrue($form->has('testEmail'));
        $this->assertTrue($form->has('forceResend'));
    }

    public function testCsvFileIsRequired(): void
    {
        $form = $this->factory->create(CsvUploadType::class);
        $this->assertTrue($form->get('csvFile')->getConfig()->getRequired());
    }
}
