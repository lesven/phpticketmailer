<?php

namespace App\Repository;

interface EmailSentReadRepositoryInterface
{
    /**
     * Liefert rohe Monats-/Domain-Aggregate ab einem Startdatum.
     *
     * @param string $distinctField 'username' oder 'ticket_id'
     * @param \DateTimeImmutable|null $since Startzeitpunkt für die Abfrage (inklusive). Falls null, wird der Standardzeitraum (letzte 6 Monate) genutzt.
     * @return array Rohformat: [['month' => 'YYYY-MM', 'domains' => ['domain.tld' => count, ...], 'total_*' => int], ...]
     */
    public function getMonthlyDomainCountsRaw(string $distinctField, ?\DateTimeImmutable $since = null): array;
}
