<?php

namespace App\Dto;

/**
 * Value Object für Paginierungsergebnisse
 * 
 * Kapselt alle Informationen über ein paginiertes Ergebnis
 * und stellt hilfreiche Methoden für die Anzeige bereit.
 */
readonly class PaginationResult
{
    public function __construct(
        public array $results,
        public int $currentPage,
        public int $totalPages,
        public int $totalItems,
        public int $itemsPerPage,
        public bool $hasNext,
        public bool $hasPrevious
    ) {
    }

    /**
     * Gibt die Anzahl der Ergebnisse auf der aktuellen Seite zurück
     */
    public function getCurrentPageItemCount(): int
    {
        return count($this->results);
    }

    /**
     * Gibt die Start-Position für die aktuelle Seite zurück (1-basiert)
     */
    public function getStartPosition(): int
    {
        if ($this->totalItems === 0) {
            return 0;
        }
        
        return ($this->currentPage - 1) * $this->itemsPerPage + 1;
    }

    /**
     * Gibt die End-Position für die aktuelle Seite zurück (1-basiert)
     */
    public function getEndPosition(): int
    {
        if ($this->totalItems === 0) {
            return 0;
        }
        
        return min(
            $this->getStartPosition() + $this->getCurrentPageItemCount() - 1,
            $this->totalItems
        );
    }

    /**
     * Gibt die nächste Seitennummer zurück (falls vorhanden)
     */
    public function getNextPage(): ?int
    {
        return $this->hasNext ? $this->currentPage + 1 : null;
    }

    /**
     * Gibt die vorherige Seitennummer zurück (falls vorhanden)
     */
    public function getPreviousPage(): ?int
    {
        return $this->hasPrevious ? $this->currentPage - 1 : null;
    }

    /**
     * Prüft, ob Ergebnisse vorhanden sind
     */
    public function hasResults(): bool
    {
        return !empty($this->results);
    }
}
