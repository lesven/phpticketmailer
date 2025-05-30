<?php
/**
 * CsvUploadType.php
 * 
 * Diese Formularklasse definiert das Formular zum Hochladen von CSV-Dateien
 * mit Ticket-Daten für den E-Mail-Versand. Sie enthält Validierungsregeln
 * für die hochgeladene Datei und Optionen für den Testmodus.
 * 
 * @package App\Form
 */

namespace App\Form;

use App\Entity\CsvFieldConfig;
use App\Form\CsvFieldConfigType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

/**
 * Formular für den Upload von CSV-Dateien mit Ticket-Informationen
 */
class CsvUploadType extends AbstractType
{    /**
     * Baut das Formular mit seinen Feldern und Validierungsregeln auf
     *
     * Das Formular enthält ein Feld zum Hochladen einer CSV-Datei und
     * eine Checkbox für den Testmodus. Die Datei wird auf gültige MIME-Typen
     * und maximale Größe validiert.
     * 
     * @param FormBuilderInterface $builder Der Formular-Builder
     * @param array $options Optionen für das Formular
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            // Feld für die CSV-Datei
            ->add('csvFile', FileType::class, [
                'label' => 'CSV-Datei',
                'mapped' => false, // Nicht an eine Entitätseigenschaft gebunden
                'required' => true, // Datei ist erforderlich
                'constraints' => [
                    new File([
                        'maxSize' => '1024k', // Maximale Dateigröße: 1MB
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
                'attr' => ['class' => 'form-control'], // Bootstrap-Styling
                'help' => 'Die CSV-Datei muss die konfigurierten Spalten enthalten',
            ])
            // Eingebettetes Formular für CSV-Feldkonfiguration
            ->add('csvFieldConfig', CsvFieldConfigType::class, [
                'label' => false,
                'mapped' => false,
            ])
            // Checkbox für den Testmodus
            ->add('testMode', CheckboxType::class, [
                'label' => 'Testmodus (E-Mails werden an Test-Adresse gesendet)',
                'required' => false, // Checkbox ist optional
                'data' => true, // Standardmäßig aktiviert, um versehentliches Versenden zu vermeiden
                'attr' => ['class' => 'form-check-input'], // Bootstrap-Styling
            ]);
    }

    /**
     * Konfiguriert die Optionen für das Formular
     *
     * Da dieses Formular nicht an eine spezifische Entität gebunden ist,
     * wird data_class auf null gesetzt.
     * 
     * @param OptionsResolver $resolver Der Options-Resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => null, // Keine Entitätsklasse für dieses Formular
        ]);
    }
}