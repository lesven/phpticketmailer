<?php

namespace App\Repository;

use App\Entity\CsvFieldConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CsvFieldConfig>
 */
class CsvFieldConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CsvFieldConfig::class);
    }

    /**
     * Holt die aktuelle CSV-Konfiguration oder erstellt eine neue mit Standardwerten
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
     * Speichert die CSV-Konfiguration
     */
    public function saveConfig(CsvFieldConfig $config): void
    {
        $this->getEntityManager()->persist($config);
        $this->getEntityManager()->flush();
    }
}
