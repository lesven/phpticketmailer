<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
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
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}