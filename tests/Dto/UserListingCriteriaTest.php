<?php

namespace App\Tests\Dto;

use App\Dto\UserListingCriteria;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class UserListingCriteriaTest extends TestCase
{
    public function testFromRequestWithDefaults(): void
    {
        $request = Request::create('/users');

        $criteria = UserListingCriteria::fromRequest($request);

        $this->assertNull($criteria->searchTerm);
        $this->assertSame('id', $criteria->sortField);
        $this->assertSame('ASC', $criteria->sortDirection);
        $this->assertSame(1, $criteria->page);
    }

    public function testFromRequestWithSearchTerm(): void
    {
        $request = Request::create('/users', 'GET', ['search' => 'anna']);

        $criteria = UserListingCriteria::fromRequest($request);

        $this->assertSame('anna', $criteria->searchTerm);
    }

    public function testFromRequestTrimsWhitespaceFromSearch(): void
    {
        $request = Request::create('/users', 'GET', ['search' => '  bob  ']);

        $criteria = UserListingCriteria::fromRequest($request);

        $this->assertSame('bob', $criteria->searchTerm);
    }

    public function testFromRequestEmptySearchBecomesNull(): void
    {
        $request = Request::create('/users', 'GET', ['search' => '   ']);

        $criteria = UserListingCriteria::fromRequest($request);

        $this->assertNull($criteria->searchTerm);
    }

    public function testFromRequestNormalizesDirectionToUppercase(): void
    {
        $requestAsc = Request::create('/users', 'GET', ['direction' => 'asc']);
        $requestDesc = Request::create('/users', 'GET', ['direction' => 'desc']);

        $this->assertSame('ASC', UserListingCriteria::fromRequest($requestAsc)->sortDirection);
        $this->assertSame('DESC', UserListingCriteria::fromRequest($requestDesc)->sortDirection);
    }

    public function testFromRequestInvalidDirectionDefaultsToAsc(): void
    {
        $request = Request::create('/users', 'GET', ['direction' => 'RANDOM']);

        $criteria = UserListingCriteria::fromRequest($request);

        $this->assertSame('ASC', $criteria->sortDirection);
    }

    public function testFromRequestCustomSortField(): void
    {
        $request = Request::create('/users', 'GET', ['sort' => 'email']);

        $criteria = UserListingCriteria::fromRequest($request);

        $this->assertSame('email', $criteria->sortField);
    }

    public function testFromRequestPageIsAtLeastOne(): void
    {
        $requestZero = Request::create('/users', 'GET', ['page' => '0']);
        $requestNegative = Request::create('/users', 'GET', ['page' => '-5']);
        $requestValid = Request::create('/users', 'GET', ['page' => '3']);

        $this->assertSame(1, UserListingCriteria::fromRequest($requestZero)->page);
        $this->assertSame(1, UserListingCriteria::fromRequest($requestNegative)->page);
        $this->assertSame(3, UserListingCriteria::fromRequest($requestValid)->page);
    }

    public function testHasSearchTermReturnsTrueWhenSet(): void
    {
        $criteria = new UserListingCriteria('test', 'id', 'ASC', 1);

        $this->assertTrue($criteria->hasSearchTerm());
    }

    public function testHasSearchTermReturnsFalseWhenNull(): void
    {
        $criteria = new UserListingCriteria(null, 'id', 'ASC', 1);

        $this->assertFalse($criteria->hasSearchTerm());
    }
}
