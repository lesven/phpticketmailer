<?php

namespace App\Repository;

use App\Entity\EmailSent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmailSent>
 */
class EmailSentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailSent::class);
    }

    public function findRecentEmails(int $limit = 10): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.timestamp', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}