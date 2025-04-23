<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class CsvUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('csvFile', FileType::class, [
                'label' => 'CSV-Datei',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => '1024k',
                        'mimeTypes' => [
                            'text/csv',
                            'text/plain',
                            'application/csv',
                            'application/x-csv',
                            'text/comma-separated-values',
                            'text/x-comma-separated-values',
                        ],
                        'mimeTypesMessage' => 'Bitte laden Sie eine gültige CSV-Datei hoch',
                    ])
                ],
                'attr' => ['class' => 'form-control'],
                'help' => 'Die CSV-Datei muss folgende Spalten enthalten: ticketId, username, ticketName',
            ])
            ->add('testMode', CheckboxType::class, [
                'label' => 'Testmodus (E-Mails werden an Test-Adresse gesendet)',
                'required' => false,
                'data' => true, // Standardmäßig aktiviert, um versehentliches Versenden zu vermeiden
                'attr' => ['class' => 'form-check-input'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}