<?php

namespace App\Form;

use App\Entity\SMTPConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SMTPConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('host', TextType::class, [
                'label' => 'SMTP Host',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'z.B. smtp.example.com'
                ]
            ])
            ->add('port', IntegerType::class, [
                'label' => 'SMTP Port',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'z.B. 587 (TLS) oder 25'
                ]
            ])
            ->add('username', TextType::class, [
                'label' => 'Benutzername',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Optional: SMTP-Benutzername'
                ]
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Passwort',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Optional: SMTP-Passwort'
                ],
                'always_empty' => false
            ])
            ->add('useTLS', CheckboxType::class, [
                'label' => 'TLS-Verschlüsselung verwenden',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'                ],
                'label_attr' => [
                    'class' => 'form-check-label'
                ]
            ])
            ->add('senderEmail', EmailType::class, [
                'label' => 'Absender-E-Mail',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'z.B. noreply@example.com'
                ]
            ])
            ->add('senderName', TextType::class, [
                'label' => 'Absendername',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'z.B. Ticket-System'
                ]
            ])
            ->add('ticketBaseUrl', TextType::class, [
                'label' => 'Ticket-Basis-URL',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'z.B. https://www.ticket.de'
                ],
                'help' => 'Diese URL wird für die Generierung von Ticket-Links in E-Mails verwendet'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => SMTPConfig::class,
        ]);
    }
}