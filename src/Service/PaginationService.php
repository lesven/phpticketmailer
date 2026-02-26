<?php

namespace App\Service;

use App\Dto\PaginationResult;
use Doctrine\ORM\QueryBuilder;

/**
 * Service für einheitliche Paginierung in der Anwendung
 * 
 * Stellt eine zentrale Implementierung für Paginierung zur Verfügung
 * und sorgt für einheitliche Parameter und Berechnungen.
 */
class PaginationService
{
    private const DEFAULT_LIMIT = 15;

    /**
     * Erstellt paginierte Ergebnisse basierend auf einem QueryBuilder
     * 
     * @param QueryBuilder $queryBuilder Der Query Builder für die Datenbankabfrage
     * @param int $page Aktuelle Seite (1-basiert)
     * @param int $limit Anzahl Einträge pro Seite
     * 
     * @return PaginationResult Paginierungsergebnis mit Daten und Metainformationen
     */
    public function paginate(QueryBuilder $queryBuilder, int $page = 1, int $limit = self::DEFAULT_LIMIT): PaginationResult
    {
        // Seite und Limit validieren
        $page = max(1, $page);
        $limit = max(1, $limit);

        // Gesamtanzahl ermitteln (ohne LIMIT)
        $totalCountQuery = clone $queryBuilder;
        $totalCount = (int) $totalCountQuery
            ->select('COUNT(DISTINCT ' . $queryBuilder->getRootAliases()[0] . '.id)')
            ->setFirstResult(null)
            ->setMaxResults(null)
            ->getQuery()
            ->getSingleScalarResult();

        // Pagination anwenden
        $offset = ($page - 1) * $limit;
        $results = $queryBuilder
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        // Metadaten berechnen
        $totalPages = max(1, (int) ceil($totalCount / $limit));
        $currentPage = min($page, $totalPages);

        return new PaginationResult(
            results: $results,
            currentPage: $currentPage,
            totalPages: $totalPages,
            totalItems: $totalCount,
            itemsPerPage: $limit,
            hasNext: $currentPage < $totalPages,
            hasPrevious: $currentPage > 1
        );
    }

    /**
     * Berechnet die Offset-Position für eine bestimmte Seite
     */
    public function calculateOffset(int $page, int $limit = self::DEFAULT_LIMIT): int
    {
        return (max(1, $page) - 1) * max(1, $limit);
    }

    /**
     * Gibt das Standard-Limit für Paginierung zurück
     */
    public function getDefaultLimit(): int
    {
        return self::DEFAULT_LIMIT;
    }
}
