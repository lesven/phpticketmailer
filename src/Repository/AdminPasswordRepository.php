<?php
declare(strict_types=1);
/**
 * AdminPasswordRepository.php
 *
 * Diese Repository-Klasse stellt Methoden zum Zugriff auf AdminPassword-Entitäten bereit.
 * Sie verwaltet die Admin-Passwort-Konfiguration für das System, da normalerweise nur
 * eine einzige Admin-Passwort-Konfiguration existiert.
 *
 * @package App\Repository
 */

namespace App\Repository;

use App\Entity\AdminPassword;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository für die AdminPassword-Entität
 *
 * Diese Repository-Klasse bietet spezielle Methoden für den Zugriff auf
 * die Admin-Passwort-Konfiguration des Systems.
 *
 * @extends ServiceEntityRepository<AdminPassword>
 */
final class AdminPasswordRepository extends ServiceEntityRepository
{
    /**
     * Konstruktor mit Doctrine ManagerRegistry als Dependency
     *
     * @param ManagerRegistry $registry Die Doctrine ManagerRegistry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminPassword::class);
    }
    
    /**
     * Findet die erste (und normalerweise einzige) AdminPassword-Entität
     *
     * Da das System typischerweise nur eine Admin-Passwort-Konfiguration verwendet,
     * gibt diese Methode die erste gefundene Entität zurück, sortiert nach ID.
     *
     * @return AdminPassword|null Die gefundene AdminPassword-Entität oder null, wenn keine vorhanden ist
     */
    public function findFirst(): ?AdminPassword
    {
        return $this->findOneBy([], ['id' => 'ASC']);
    }
}