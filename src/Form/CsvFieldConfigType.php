<?php

namespace App\Form;

use App\Entity\CsvFieldConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class CsvFieldConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('ticketIdField', TextType::class, [
                'label' => 'Ticket-ID Spaltenname',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'ticketId (Standard)',
                    'maxlength' => 50
                ],
                'help' => 'Name der Spalte für die Ticket-ID (Standard: ticketId)',
                'constraints' => [
                    new Length(['max' => 50, 'maxMessage' => 'Der Spaltenname darf maximal 50 Zeichen lang sein'])
                ]
            ])
            ->add('usernameField', TextType::class, [
                'label' => 'Benutzername Spaltenname',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'username (Standard)',
                    'maxlength' => 50
                ],
                'help' => 'Name der Spalte für den Benutzernamen (Standard: username)',
                'constraints' => [
                    new Length(['max' => 50, 'maxMessage' => 'Der Spaltenname darf maximal 50 Zeichen lang sein'])
                ]
            ])
            ->add('ticketNameField', TextType::class, [
                'label' => 'Ticket-Name Spaltenname',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'ticketName (Standard)',
                    'maxlength' => 50
                ],
                'help' => 'Name der Spalte für den Ticket-Namen (Standard: ticketName)',
                'constraints' => [
                    new Length(['max' => 50, 'maxMessage' => 'Der Spaltenname darf maximal 50 Zeichen lang sein'])
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CsvFieldConfig::class,
        ]);
    }
}
