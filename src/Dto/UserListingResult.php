<?php
declare(strict_types=1);

namespace App\Dto;

final class UserListingResult
{
    public function __construct(
        public readonly array $users,
        public readonly ?PaginationResult $pagination,
        public readonly ?string $searchTerm,
        public readonly string $sortField,
        public readonly string $sortDirection,
    ) {}

    /**
     * Gibt zurück, ob die Liste ein Suchergebnis enthält.
     */
    public function hasSearch(): bool
    {
        return $this->searchTerm !== null;
    }

    /**
     * Liefert die entgegengesetzte Sortierrichtung für Links im Template.
     */
    public function getOppositeDirection(): string
    {
        return $this->sortDirection === 'ASC' ? 'DESC' : 'ASC';
    }
}
