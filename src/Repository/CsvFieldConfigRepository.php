<?php
/**
 * CsvFieldConfigRepository.php
 *
 * Diese Repository-Klasse stellt Methoden zum Zugriff auf CsvFieldConfig-Entitäten bereit.
 * Sie verwaltet die Konfiguration für CSV-Feld-Zuordnungen und bietet Hilfsmethoden
 * zum Laden und Speichern der aktuellen Konfiguration.
 *
 * @package App\Repository
 */

namespace App\Repository;

use App\Entity\CsvFieldConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository für die CsvFieldConfig-Entität
 *
 * Diese Repository-Klasse bietet spezielle Methoden für den Zugriff auf
 * die CSV-Feld-Konfiguration des Systems.
 *
 * @extends ServiceEntityRepository<CsvFieldConfig>
 */
class CsvFieldConfigRepository extends ServiceEntityRepository
{
    /**
     * Konstruktor mit Doctrine ManagerRegistry als Dependency
     *
     * @param ManagerRegistry $registry Die Doctrine ManagerRegistry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CsvFieldConfig::class);
    }

    /**
     * Holt die aktuelle CSV-Konfiguration oder erstellt eine neue mit Standardwerten
     *
     * Diese Methode stellt sicher, dass immer eine gültige CSV-Konfiguration vorhanden ist.
     * Falls keine Konfiguration in der Datenbank existiert, wird eine neue mit
     * Standardwerten erstellt und persistiert.
     *
     * @return CsvFieldConfig Die aktuelle oder neu erstellte CSV-Konfiguration
     */
    public function getCurrentConfig(): CsvFieldConfig
    {
        $config = $this->findOneBy([]);
        
        if (!$config) {
            $config = new CsvFieldConfig();
            $this->getEntityManager()->persist($config);
            $this->getEntityManager()->flush();
        }
        
        return $config;
    }

    /**
     * Speichert die CSV-Konfiguration in der Datenbank
     *
     * Diese Methode persistiert und schreibt die übergebene CSV-Konfiguration
     * in die Datenbank. Sie führt sowohl persist() als auch flush() aus,
     * um sicherzustellen, dass die Änderungen sofort gespeichert werden.
     *
     * @param CsvFieldConfig $config Die zu speichernde CSV-Konfiguration
     * @return void
     */
    public function saveConfig(CsvFieldConfig $config): void
    {
        $this->getEntityManager()->persist($config);
        $this->getEntityManager()->flush();
    }
}
