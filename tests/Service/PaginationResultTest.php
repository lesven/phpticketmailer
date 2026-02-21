<?php

namespace App\Tests\Service;

use App\Service\PaginationResult;
use PHPUnit\Framework\TestCase;

class PaginationResultTest extends TestCase
{
    private function makeResult(
        array $results,
        int $currentPage,
        int $totalPages,
        int $totalItems,
        int $itemsPerPage,
        bool $hasNext,
        bool $hasPrevious
    ): PaginationResult {
        return new PaginationResult(
            results: $results,
            currentPage: $currentPage,
            totalPages: $totalPages,
            totalItems: $totalItems,
            itemsPerPage: $itemsPerPage,
            hasNext: $hasNext,
            hasPrevious: $hasPrevious
        );
    }

    // --- getCurrentPageItemCount ---

    public function testGetCurrentPageItemCountReturnsZeroForEmptyResults(): void
    {
        $result = $this->makeResult([], 1, 1, 0, 15, false, false);

        $this->assertEquals(0, $result->getCurrentPageItemCount());
    }

    public function testGetCurrentPageItemCountReturnsCorrectCount(): void
    {
        $result = $this->makeResult(['a', 'b', 'c'], 1, 3, 45, 15, true, false);

        $this->assertEquals(3, $result->getCurrentPageItemCount());
    }

    public function testGetCurrentPageItemCountReturnsTotalItemsWhenOnSinglePage(): void
    {
        $items = range(1, 10);
        $result = $this->makeResult($items, 1, 1, 10, 15, false, false);

        $this->assertEquals(10, $result->getCurrentPageItemCount());
    }

    // --- getStartPosition ---

    public function testGetStartPositionReturnsZeroWhenNoItems(): void
    {
        $result = $this->makeResult([], 1, 1, 0, 15, false, false);

        $this->assertEquals(0, $result->getStartPosition());
    }

    public function testGetStartPositionReturns1ForFirstPageFirstItem(): void
    {
        $result = $this->makeResult(range(1, 15), 1, 3, 45, 15, true, false);

        $this->assertEquals(1, $result->getStartPosition());
    }

    public function testGetStartPositionReturns16ForSecondPage(): void
    {
        $result = $this->makeResult(range(16, 30), 2, 3, 45, 15, true, true);

        $this->assertEquals(16, $result->getStartPosition());
    }

    public function testGetStartPositionReturns31ForThirdPage(): void
    {
        $result = $this->makeResult(range(31, 45), 3, 3, 45, 15, false, true);

        $this->assertEquals(31, $result->getStartPosition());
    }

    // --- getEndPosition ---

    public function testGetEndPositionReturnsZeroWhenNoItems(): void
    {
        $result = $this->makeResult([], 1, 1, 0, 15, false, false);

        $this->assertEquals(0, $result->getEndPosition());
    }

    public function testGetEndPositionReturns15ForFirstFullPage(): void
    {
        $result = $this->makeResult(range(1, 15), 1, 3, 45, 15, true, false);

        $this->assertEquals(15, $result->getEndPosition());
    }

    public function testGetEndPositionReturns30ForSecondFullPage(): void
    {
        $result = $this->makeResult(range(16, 30), 2, 3, 45, 15, true, true);

        $this->assertEquals(30, $result->getEndPosition());
    }

    public function testGetEndPositionReturnsCorrectValueForPartialLastPage(): void
    {
        // Total 32 items, page size 15: last page has 2 items
        $result = $this->makeResult(range(31, 32), 3, 3, 32, 15, false, true);

        $this->assertEquals(32, $result->getEndPosition());
    }

    // --- getNextPage ---

    public function testGetNextPageReturnsNullWhenNoNextPage(): void
    {
        $result = $this->makeResult([], 3, 3, 45, 15, false, true);

        $this->assertNull($result->getNextPage());
    }

    public function testGetNextPageReturnsNextPageNumber(): void
    {
        $result = $this->makeResult(range(1, 15), 1, 3, 45, 15, true, false);

        $this->assertEquals(2, $result->getNextPage());
    }

    public function testGetNextPageReturnsCorrectPageFromMiddle(): void
    {
        $result = $this->makeResult(range(16, 30), 2, 4, 60, 15, true, true);

        $this->assertEquals(3, $result->getNextPage());
    }

    // --- getPreviousPage ---

    public function testGetPreviousPageReturnsNullWhenNoPreviousPage(): void
    {
        $result = $this->makeResult(range(1, 15), 1, 3, 45, 15, true, false);

        $this->assertNull($result->getPreviousPage());
    }

    public function testGetPreviousPageReturnsPreviousPageNumber(): void
    {
        $result = $this->makeResult(range(16, 30), 2, 3, 45, 15, true, true);

        $this->assertEquals(1, $result->getPreviousPage());
    }

    public function testGetPreviousPageReturnsCorrectPageFromMiddle(): void
    {
        $result = $this->makeResult(range(31, 45), 3, 4, 60, 15, true, true);

        $this->assertEquals(2, $result->getPreviousPage());
    }

    // --- hasResults ---

    public function testHasResultsReturnsFalseForEmptyResults(): void
    {
        $result = $this->makeResult([], 1, 1, 0, 15, false, false);

        $this->assertFalse($result->hasResults());
    }

    public function testHasResultsReturnsTrueWhenResultsExist(): void
    {
        $result = $this->makeResult(['item1', 'item2'], 1, 1, 2, 15, false, false);

        $this->assertTrue($result->hasResults());
    }

    // --- Public properties ---

    public function testPublicPropertiesAreAccessible(): void
    {
        $items = ['a', 'b'];
        $result = $this->makeResult($items, 2, 5, 75, 15, true, true);

        $this->assertSame($items, $result->results);
        $this->assertEquals(2, $result->currentPage);
        $this->assertEquals(5, $result->totalPages);
        $this->assertEquals(75, $result->totalItems);
        $this->assertEquals(15, $result->itemsPerPage);
        $this->assertTrue($result->hasNext);
        $this->assertTrue($result->hasPrevious);
    }

    // --- Edge case: single item ---

    public function testSingleItemSinglePage(): void
    {
        $result = $this->makeResult(['only-item'], 1, 1, 1, 15, false, false);

        $this->assertEquals(1, $result->getCurrentPageItemCount());
        $this->assertEquals(1, $result->getStartPosition());
        $this->assertEquals(1, $result->getEndPosition());
        $this->assertNull($result->getNextPage());
        $this->assertNull($result->getPreviousPage());
        $this->assertTrue($result->hasResults());
    }
}
