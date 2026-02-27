<?php
declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'Benutzername',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Benutzername eingeben'
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-Mail-Adresse',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'E-Mail-Adresse eingeben'
                ]
            ])
            ->add('excludedFromSurveys', CheckboxType::class, [
                'label' => 'Von Umfragen ausgeschlossen',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
                'help' => 'Aktivieren, wenn dieser Benutzer keine Umfrage-E-Mails erhalten soll.'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}