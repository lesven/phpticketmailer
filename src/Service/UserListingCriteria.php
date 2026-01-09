<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

final class UserListingCriteria
{
    public function __construct(
        public readonly ?string $searchTerm,
        public readonly string $sortField,
        public readonly string $sortDirection,
        public readonly int $page,
    ) {}

    /**
     * Erstellt Kriterien aus dem Request-Query, wäscht Suchterm und normalisiert Richtung & Seite.
     */
    public static function fromRequest(Request $request): self
    {
        $search = $request->query->get('search');
        if ($search !== null) {
            $search = trim($search);
            if ($search === '') {
                $search = null;
            }
        }

        $direction = strtoupper((string) $request->query->get('direction', 'ASC'));
        $sortDirection = $direction === 'DESC' ? 'DESC' : 'ASC';

        return new self(
            searchTerm: $search,
            sortField: (string) $request->query->get('sort', 'id'),
            sortDirection: $sortDirection,
            page: max(1, (int) $request->query->get('page', 1)),
        );
    }

    /**
     * Gibt zurück, ob ein Suchbegriff gesetzt wurde.
     */
    public function hasSearchTerm(): bool
    {
        return $this->searchTerm !== null;
    }
}
