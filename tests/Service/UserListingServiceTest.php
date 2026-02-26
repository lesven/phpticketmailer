<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\PaginationService;
use App\Dto\PaginationResult;
use App\Dto\UserListingCriteria;
use App\Service\UserListingService;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class UserListingServiceTest extends TestCase
{
    private UserRepository $userRepository;
    private PaginationService $paginationService;
    private UserListingService $listingService;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->paginationService = $this->createMock(PaginationService::class);
        $this->listingService = new UserListingService($this->userRepository, $this->paginationService);
    }

    public function testReturnsSearchResultsWhenTermProvided(): void
    {
        $criteria = new UserListingCriteria('john', 'email', 'ASC', 3);
        $users = [new User(), new User()];

        $this->userRepository->expects($this->once())
            ->method('searchByUsername')
            ->with('john', 'email', 'ASC')
            ->willReturn($users);
        $this->paginationService->expects($this->never())->method('paginate');

        $result = $this->listingService->listUsers($criteria);

        $this->assertSame($users, $result->users);
        $this->assertNull($result->pagination);
        $this->assertEquals('john', $result->searchTerm);
        $this->assertTrue($result->hasSearch());
    }

    public function testUsesPaginationWhenNoSearchTerm(): void
    {
        $criteria = new UserListingCriteria(null, 'username', 'DESC', 2);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $users = [new User()];

        $this->userRepository->expects($this->once())
            ->method('createSortedQueryBuilder')
            ->with('username', 'DESC')
            ->willReturn($queryBuilder);

        $paginationResult = new PaginationResult(
            results: $users,
            currentPage: 2,
            totalPages: 3,
            totalItems: 5,
            itemsPerPage: 10,
            hasNext: true,
            hasPrevious: true
        );

        $this->paginationService->expects($this->once())
            ->method('paginate')
            ->with($queryBuilder, 2)
            ->willReturn($paginationResult);

        $result = $this->listingService->listUsers($criteria);

        $this->assertSame($users, $result->users);
        $this->assertSame($paginationResult, $result->pagination);
        $this->assertNull($result->searchTerm);
        $this->assertFalse($result->hasSearch());
    }
}
