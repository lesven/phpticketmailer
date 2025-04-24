<?php

namespace App\Repository;

use App\Entity\AdminPassword;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdminPassword>
 */
class AdminPasswordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminPassword::class);
    }
    
    public function findFirst(): ?AdminPassword
    {
        return $this->findOneBy([], ['id' => 'ASC']);
    }
}