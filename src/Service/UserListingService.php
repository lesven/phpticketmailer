<?php

namespace App\Service;

use App\Dto\UserListingCriteria;
use App\Dto\UserListingResult;
use App\Repository\UserRepository;
use App\Service\PaginationService;

class UserListingService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PaginationService $paginationService
    ) {}

    /**
     * Gibt Suchergebnisse oder paginierte Treffer basierend auf dem übergebenen Kriterium zurück.
     */
    public function listUsers(UserListingCriteria $criteria): UserListingResult
    {
        if ($criteria->hasSearchTerm()) {
            $users = $this->userRepository->searchByUsername(
                $criteria->searchTerm,
                $criteria->sortField,
                $criteria->sortDirection
            );

            return new UserListingResult(
                users: $users,
                pagination: null,
                searchTerm: $criteria->searchTerm,
                sortField: $criteria->sortField,
                sortDirection: $criteria->sortDirection
            );
        }

        $queryBuilder = $this->userRepository->createSortedQueryBuilder(
            $criteria->sortField,
            $criteria->sortDirection
        );

        $paginationResult = $this->paginationService->paginate(
            $queryBuilder,
            $criteria->page
        );

        return new UserListingResult(
            users: $paginationResult->results,
            pagination: $paginationResult,
            searchTerm: null,
            sortField: $criteria->sortField,
            sortDirection: $criteria->sortDirection
        );
    }
}
