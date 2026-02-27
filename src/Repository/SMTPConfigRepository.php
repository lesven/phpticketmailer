<?php
declare(strict_types=1);
/**
 * SMTPConfigRepository.php
 * 
 * Diese Repository-Klasse stellt Methoden zum Zugriff auf die SMTP-Konfiguration bereit.
 * Da das System nur eine einzige SMTP-Konfiguration verwendet, bietet dieses Repository
 * eine spezielle Methode, um genau diese Konfiguration abzurufen.
 * 
 * @package App\Repository
 */

namespace App\Repository;

use App\Entity\SMTPConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository für die SMTPConfig-Entität
 * 
 * @extends ServiceEntityRepository<SMTPConfig>
 */
final class SMTPConfigRepository extends ServiceEntityRepository
{
    /**
     * Konstruktor mit Doctrine ManagerRegistry als Dependency
     * 
     * @param ManagerRegistry $registry Die Doctrine ManagerRegistry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SMTPConfig::class);
    }

    /**
     * Holt die aktive SMTP-Konfiguration aus der Datenbank
     * 
     * Da das System nur eine einzige SMTP-Konfiguration verwendet,
     * gibt diese Methode einfach den ersten Datensatz zurück,
     * der in der Datenbank gefunden wird.
     * 
     * @return SMTPConfig|null Die gefundene SMTP-Konfiguration oder null, wenn keine vorhanden ist
     */
    public function getConfig(): ?SMTPConfig
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}