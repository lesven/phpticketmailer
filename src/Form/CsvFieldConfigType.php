<?php
/**
 * CsvFieldConfigType.php
 *
 * Diese Symfony Form-Klasse definiert das Formular zur Konfiguration der
 * CSV-Feld-Zuordnungen. Sie ermöglicht es Benutzern, die Namen der CSV-Spalten
 * anzupassen, die den verschiedenen Datenfeldern entsprechen.
 *
 * @package App\Form
 */

namespace App\Form;

use App\Entity\CsvFieldConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Symfony Form Type für die CSV-Feld-Konfiguration
 *
 * Definiert ein Formular mit vier Textfeldern zur Konfiguration der
 * CSV-Spalten-Namen für Ticket-ID, Benutzername, Ticket-Namen und Erstellungsdatum.
 */
class CsvFieldConfigType extends AbstractType
{
    /**
     * Erstellt das Formular mit den benötigten Feldern
     *
     * Konfiguriert drei Textfelder für die CSV-Spalten-Zuordnung:
     * - ticketIdField: Spalte für die Ticket-ID
     * - usernameField: Spalte für den Benutzernamen
     * - ticketNameField: Spalte für den Ticket-Namen
     *
     * Alle Felder haben eine maximale Länge von 50 Zeichen und sind optional,
     * da Standardwerte verwendet werden, wenn keine Werte angegeben sind.
     *
     * @param FormBuilderInterface $builder Das Formular-Builder-Interface
     * @param array $options Die Formular-Optionen
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('ticketIdField', TextType::class, [
                'label' => 'Ticket-ID Spaltenname',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Vorgangsschlüssel (Standard)',
                    'maxlength' => 50
                ],
                'help' => 'Name der Spalte für die Ticket-ID (Standard: Vorgangsschlüssel)',
                'constraints' => [
                    new Length(max: 50, maxMessage: 'Der Spaltenname darf maximal 50 Zeichen lang sein')
                ]
            ])
            ->add('usernameField', TextType::class, [
                'label' => 'Benutzername Spaltenname',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Autor (Standard)',
                    'maxlength' => 50
                ],
                'help' => 'Name der Spalte für den Benutzernamen (Standard: Autor)',
                'constraints' => [
                    new Length(max: 50, maxMessage: 'Der Spaltenname darf maximal 50 Zeichen lang sein')
                ]
            ])
            ->add('ticketNameField', TextType::class, [
                'label' => 'Ticket-Name Spaltenname',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Zusammenfassung (Standard)',
                    'maxlength' => 50
                ],
                'help' => 'Name der Spalte für den Ticket-Namen (Standard: Zusammenfassung)',
                'constraints' => [
                    new Length(max: 50, maxMessage: 'Der Spaltenname darf maximal 50 Zeichen lang sein')
                ]
            ])

            ->add('createdField', TextType::class, [
                'label' => 'Erstellungsdatum Spaltenname',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Erstellt (Standard)',
                    'maxlength' => 50
                ],
                'help' => 'Name der Spalte für das Erstellungsdatum (Standard: Erstellt)',
                'constraints' => [
                    new Length(max: 50, maxMessage: 'Der Spaltenname darf maximal 50 Zeichen lang sein')
                ]
            ]);
    }

    /**
     * Konfiguriert die Standard-Optionen für das Formular
     *
     * Setzt die data_class auf CsvFieldConfig, damit das Formular
     * automatisch mit CsvFieldConfig-Entitäten arbeiten kann.
     *
     * @param OptionsResolver $resolver Der Options-Resolver
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CsvFieldConfig::class,
        ]);
    }
}
