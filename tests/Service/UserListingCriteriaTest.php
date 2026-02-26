<?php

namespace App\Tests\Service;

use App\Dto\UserListingCriteria;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class UserListingCriteriaTest extends TestCase
{
    public function testFromRequestAppliesDefaults(): void
    {
        $criteria = UserListingCriteria::fromRequest(new Request());

        $this->assertNull($criteria->searchTerm);
        $this->assertEquals('id', $criteria->sortField);
        $this->assertEquals('ASC', $criteria->sortDirection);
        $this->assertEquals(1, $criteria->page);
        $this->assertFalse($criteria->hasSearchTerm());
    }

    public function testFromRequestRespectsCustomValues(): void
    {
        $request = new Request([
            'search' => '  john  ',
            'sort' => 'username',
            'direction' => 'desc',
            'page' => '-3',
        ]);

        $criteria = UserListingCriteria::fromRequest($request);

        $this->assertSame('john', $criteria->searchTerm);
        $this->assertEquals('username', $criteria->sortField);
        $this->assertEquals('DESC', $criteria->sortDirection);
        $this->assertEquals(1, $criteria->page);
        $this->assertTrue($criteria->hasSearchTerm());
    }
}
