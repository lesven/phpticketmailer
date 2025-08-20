<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Exception\CsvProcessingException;

final class CsvProcessingExceptionTest extends TestCase
{
    public function test_invalidStructure_and_usermessage(): void
    {
        $ex = CsvProcessingException::invalidStructure('Spaltenreihenfolge falsch', ['extra' => 1]);

        $this->assertInstanceOf(CsvProcessingException::class, $ex);
        $this->assertStringContainsString('ungültige Struktur', $ex->getMessage());
        $this->assertSame(['extra' => 1, 'type' => 'invalid_structure'], $ex->getContext());
        $this->assertSame('Die hochgeladene CSV-Datei hat ein ungültiges Format. Bitte überprüfen Sie die Datei.', $ex->getUserMessage());
    }

    public function test_missingColumns_and_usermessage(): void
    {
        $ex = CsvProcessingException::missingColumns(['email', 'name']);

        $this->assertInstanceOf(CsvProcessingException::class, $ex);
        $this->assertStringContainsString('Folgende erforderliche Spalten fehlen', $ex->getMessage());
        $this->assertSame('Die CSV-Datei enthält nicht alle erforderlichen Spalten.', $ex->getUserMessage());
        $this->assertArrayHasKey('missing_columns', $ex->getContext());
        $this->assertSame(['email','name'], $ex->getContext()['missing_columns']);
    }

    public function test_emptyFile_and_usermessage(): void
    {
        $ex = CsvProcessingException::emptyFile();
        $this->assertSame('Die hochgeladene Datei ist leer.', $ex->getUserMessage());
    }

    public function test_fileReadError_preserves_previous_and_usermessage(): void
    {
        $prev = new \RuntimeException('io');
        $ex = CsvProcessingException::fileReadError('test.csv', $prev);

        $this->assertSame('Die CSV-Datei \'test.csv\' konnte nicht gelesen werden', $ex->getMessage());
        $this->assertSame($prev, $ex->getPrevious());
        $this->assertSame('Die Datei konnte nicht gelesen werden. Bitte versuchen Sie es erneut.', $ex->getUserMessage());
        $this->assertSame(['type' => 'file_read_error', 'filename' => 'test.csv'], $ex->getContext());
    }
}
