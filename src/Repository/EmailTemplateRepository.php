<?php

namespace App\Repository;

use App\Entity\EmailTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmailTemplate>
 */
class EmailTemplateRepository extends ServiceEntityRepository
{
    /**
     * @param ManagerRegistry $registry Die Doctrine-Registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailTemplate::class);
    }

    /**
     * Alle Templates sortiert nach validFrom (neueste zuerst)
     *
     * @return EmailTemplate[]
     */
    public function findAllOrderedByValidFrom(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.validFrom', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Findet das passende Template für ein gegebenes Ticket-Erstelldatum.
     * Das Template mit dem größten validFrom <= $date wird gewählt,
     * d.h. das Template, das zum Zeitpunkt der Ticket-Erstellung aktiv war.
     */
    public function findActiveTemplateForDate(\DateTimeInterface $date): ?EmailTemplate
    {
        return $this->createQueryBuilder('t')
            ->where('t.validFrom <= :date')
            ->setParameter('date', $date, Types::DATE_MUTABLE)
            ->orderBy('t.validFrom', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Findet das neueste Template (Fallback wenn kein Datum gegeben).
     */
    public function findLatestTemplate(): ?EmailTemplate
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.validFrom', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Speichert ein Template in der Datenbank (persist + optional flush).
     *
     * @param EmailTemplate $template Das zu speichernde Template
     * @param bool $flush Ob sofort geflusht werden soll (default: true)
     */
    public function save(EmailTemplate $template, bool $flush = true): void
    {
        $this->getEntityManager()->persist($template);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Entfernt ein Template aus der Datenbank (remove + optional flush).
     *
     * @param EmailTemplate $template Das zu löschende Template
     * @param bool $flush Ob sofort geflusht werden soll (default: true)
     */
    public function remove(EmailTemplate $template, bool $flush = true): void
    {
        $this->getEntityManager()->remove($template);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
