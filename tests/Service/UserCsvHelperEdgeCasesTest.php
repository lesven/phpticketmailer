<?php

namespace App\Tests\Service;

use App\Service\UserCsvHelper;
use PHPUnit\Framework\TestCase;

class UserCsvHelperEdgeCasesTest extends TestCase
{
    public function testMapRowToUserDataThrowsWhenColumnIndicesAreMissing(): void
    {
        $helper = new UserCsvHelper();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing column indices for username or email');

        $helper->mapRowToUserData(['john', 'john@example.com'], ['username' => 0]);
    }

    public function testMapRowToUserDataThrowsWhenRowDoesNotContainExpectedColumns(): void
    {
        $helper = new UserCsvHelper();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Row does not contain the required columns');

        $helper->mapRowToUserData(['john'], ['username' => 0, 'email' => 2]);
    }

    /**
     * @dataProvider csvFieldProvider
     */
    public function testEscapeCsvFieldEscapesAndWrapsAsExpected(string $rawField, string $expected): void
    {
        $helper = new UserCsvHelper();

        $this->assertSame($expected, $helper->escapeCsvField($rawField));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function csvFieldProvider(): array
    {
        return [
            'simple field' => ['alice', '"alice"'],
            'contains quote' => ['a"b', '"a""b"'],
            'contains comma' => ['a,b', '"a,b"'],
            'contains newline' => ["a\n b", "\"a\n b\""],
            'empty string' => ['', '""'],
        ];
    }
}
