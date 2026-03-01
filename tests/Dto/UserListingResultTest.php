<?php

namespace App\Tests\Dto;

use App\Dto\PaginationResult;
use App\Dto\UserListingResult;
use PHPUnit\Framework\TestCase;

class UserListingResultTest extends TestCase
{
    public function testHasSearchReturnsTrueWhenSearchTermSet(): void
    {
        $result = new UserListingResult(
            users: [],
            pagination: null,
            searchTerm: 'anna',
            sortField: 'id',
            sortDirection: 'ASC'
        );

        $this->assertTrue($result->hasSearch());
    }

    public function testHasSearchReturnsFalseWhenSearchTermNull(): void
    {
        $result = new UserListingResult(
            users: [],
            pagination: null,
            searchTerm: null,
            sortField: 'id',
            sortDirection: 'ASC'
        );

        $this->assertFalse($result->hasSearch());
    }

    public function testGetOppositeDirectionAscToDesc(): void
    {
        $result = new UserListingResult([], null, null, 'username', 'ASC');

        $this->assertSame('DESC', $result->getOppositeDirection());
    }

    public function testGetOppositeDirectionDescToAsc(): void
    {
        $result = new UserListingResult([], null, null, 'username', 'DESC');

        $this->assertSame('ASC', $result->getOppositeDirection());
    }

    public function testConstructorStoresAllProperties(): void
    {
        $pagination = new PaginationResult(
            results: [],
            currentPage: 2,
            totalPages: 5,
            totalItems: 50,
            itemsPerPage: 10,
            hasNext: true,
            hasPrevious: true
        );

        $users = [['id' => 1], ['id' => 2]];

        $result = new UserListingResult(
            users: $users,
            pagination: $pagination,
            searchTerm: 'foo',
            sortField: 'email',
            sortDirection: 'DESC'
        );

        $this->assertSame($users, $result->users);
        $this->assertSame($pagination, $result->pagination);
        $this->assertSame('foo', $result->searchTerm);
        $this->assertSame('email', $result->sortField);
        $this->assertSame('DESC', $result->sortDirection);
    }
}
