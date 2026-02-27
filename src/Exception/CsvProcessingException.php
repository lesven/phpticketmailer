<?php
declare(strict_types=1);

namespace App\Exception;

/**
 * Exception für Fehler bei der CSV-Verarbeitung
 * 
 * Wird geworfen, wenn beim Lesen, Parsen oder Validieren
 * von CSV-Dateien Probleme auftreten.
 */
final class CsvProcessingException extends TicketMailerException
{
    /**
     * Erstellt eine Exception für ungültige CSV-Strukturen
     */
    public static function invalidStructure(string $details, array $context = []): self
    {
        return new self(
            message: "Die CSV-Datei hat eine ungültige Struktur: {$details}",
            context: array_merge($context, ['type' => 'invalid_structure'])
        );
    }

    /**
     * Erstellt eine Exception für fehlende erforderliche Spalten
     */
    public static function missingColumns(array $missingColumns, array $context = []): self
    {
        $columnList = implode(', ', $missingColumns);
        
        return new self(
            message: "Folgende erforderliche Spalten fehlen in der CSV-Datei: {$columnList}",
            context: array_merge($context, [
                'type' => 'missing_columns',
                'missing_columns' => $missingColumns
            ])
        );
    }

    /**
     * Erstellt eine Exception für leere CSV-Dateien
     */
    public static function emptyFile(array $context = []): self
    {
        return new self(
            message: 'Die CSV-Datei ist leer oder enthält keine gültigen Daten',
            context: array_merge($context, ['type' => 'empty_file'])
        );
    }

    /**
     * Erstellt eine Exception für Datei-I/O-Probleme
     */
    public static function fileReadError(string $filename, ?\Throwable $previous = null): self
    {
        return new self(
            message: "Die CSV-Datei '{$filename}' konnte nicht gelesen werden",
            previous: $previous,
            context: ['type' => 'file_read_error', 'filename' => $filename]
        );
    }

    public function getUserMessage(): string
    {
        $context = $this->getContext();
        
        return match ($context['type'] ?? 'unknown') {
            'invalid_structure' => 'Die hochgeladene CSV-Datei hat ein ungültiges Format. Bitte überprüfen Sie die Datei.',
            'missing_columns' => 'Die CSV-Datei enthält nicht alle erforderlichen Spalten.',
            'empty_file' => 'Die hochgeladene Datei ist leer.',
            'file_read_error' => 'Die Datei konnte nicht gelesen werden. Bitte versuchen Sie es erneut.',
            default => 'Bei der Verarbeitung der CSV-Datei ist ein Fehler aufgetreten.'
        };
    }
}
