<?php

namespace App\Service;

use App\Exception\CsvProcessingException;
use App\Exception\InvalidEmailAddressException;
use App\Exception\InvalidTicketIdException;
use App\ValueObject\EmailAddress;
use App\ValueObject\TicketId;

/**
 * Service für die Validierung von CSV-Daten
 * 
 * Zentralisiert alle Validierungslogik für CSV-Dateien und sorgt für
 * einheitliche Fehlerbehandlung und Datenqualitätsprüfungen.
 */
class CsvValidationService
{
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB
    private const ALLOWED_MIME_TYPES = [
        'text/csv',
        'text/plain',
        'application/csv',
        'application/vnd.ms-excel'
    ];

    /**
     * Validiert eine hochgeladene CSV-Datei grundlegend
     * 
     * @param \SplFileInfo $file Die zu validierende Datei
     * @throws CsvProcessingException Bei Validierungsfehlern
     */
    public function validateUploadedFile(\SplFileInfo $file): void
    {
        // Dateigröße prüfen
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw CsvProcessingException::invalidStructure(
                'Die Datei ist zu groß (max. 10 MB)',
                ['file_size' => $file->getSize()]
            );
        }

        // Datei-MIME-Type prüfen (falls verfügbar)
        if (method_exists($file, 'getMimeType')) {
            $mimeType = $file->getMimeType();
            if ($mimeType && !in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
                throw CsvProcessingException::invalidStructure(
                    'Ungültiger Dateityp. Nur CSV-Dateien sind erlaubt.',
                    ['mime_type' => $mimeType]
                );
            }
        }

        // Datei-Erweiterung prüfen
        $extension = strtolower($file->getExtension());
        if (!in_array($extension, ['csv', 'txt'])) {
            throw CsvProcessingException::invalidStructure(
                'Ungültige Datei-Erweiterung. Nur .csv und .txt Dateien sind erlaubt.',
                ['extension' => $extension]
            );
        }
    }

    /**
     * Validiert die Struktur der CSV-Daten
     * 
     * @param array $headers Die Header-Zeile der CSV
     * @param array $requiredColumns Die erforderlichen Spalten
     * @throws CsvProcessingException Bei fehlenden Spalten
     */
    public function validateCsvStructure(array $headers, array $requiredColumns): void
    {
        $missingColumns = [];
        
        foreach ($requiredColumns as $column) {
            if (!in_array($column, $headers)) {
                $missingColumns[] = $column;
            }
        }

        if (!empty($missingColumns)) {
            throw CsvProcessingException::missingColumns($missingColumns, [
                'headers' => $headers,
                'required' => $requiredColumns
            ]);
        }
    }

    /**
     * Validiert eine einzelne CSV-Datenzeile
     * 
     * @param array $row Die zu validierende Datenzeile
     * @param array $requiredFields Die erforderlichen Felder
     * @param int $lineNumber Die Zeilennummer für Fehlerberichte
     * @return array Validierungsergebnis mit 'valid' (bool) und 'errors' (array)
     */
    public function validateCsvRow(array $row, array $requiredFields, int $lineNumber): array
    {
        $errors = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($row[$field]) || trim($row[$field]) === '') {
                $errors[] = "Feld '{$field}' ist leer oder fehlt";
            }
        }

        // Spezielle Validierungen
        if (isset($row['email']) && !empty($row['email'])) {
            if (!$this->isValidEmail($row['email'])) {
                $errors[] = "Ungültige E-Mail-Adresse: {$row['email']}";
            }
        }

        if (isset($row['ticketId']) && !empty($row['ticketId'])) {
            if (!$this->isValidTicketId($row['ticketId'])) {
                $errors[] = "Ungültige Ticket-ID: {$row['ticketId']}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'line_number' => $lineNumber
        ];
    }

    /**
     * Prüft, ob eine E-Mail-Adresse gültig ist
     * 
     * Verwendet das EmailAddress Value Object für eine umfassende Validierung
     * inklusive Normalisierung, Sicherheitsprüfungen und RFC-Konformität.
     * 
     * @param string $email Die zu prüfende E-Mail-Adresse
     * @return bool True, wenn die E-Mail gültig ist
     */
    public function isValidEmail(string $email): bool
    {
        try {
            EmailAddress::fromString($email);
            return true;
        } catch (InvalidEmailAddressException $e) {
            return false;
        }
    }

    /**
     * Prüft, ob eine Ticket-ID gültig ist
     * 
     * Behält die ursprüngliche, strengere Validierung bei,
     * um Breaking Changes zu vermeiden.
     * 
     * @param string $ticketId Die zu prüfende Ticket-ID
     * @return bool True, wenn die Ticket-ID gültig ist
     */
    public function isValidTicketId(string $ticketId): bool
    {
        // Ticket-ID sollte nicht leer sein und nur erlaubte Zeichen enthalten
        return !empty($ticketId) && 
               strlen($ticketId) <= 50 && 
               preg_match('/^[a-zA-Z0-9\-_]+$/', $ticketId);
    }

    /**
     * Bereinigt und normalisiert CSV-Daten
     * 
     * @param array $data Die zu bereinigenden Daten
     * @return array Die bereinigten Daten
     */
    public function sanitizeCsvData(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // Whitespace entfernen und HTML-Entitäten dekodieren
                $value = trim(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
            }
            
            $sanitized[$key] = $value;
        }
        
        return $sanitized;
    }

    /**
     * Entfernt Duplikate basierend auf einem Schlüssel-Feld
     * 
     * @param array $records Die zu deduplizierenden Datensätze
     * @param string $keyField Das Feld für die Duplikatserkennung
     * @return array Die deduplizierten Datensätze
     */
    public function removeDuplicates(array $records, string $keyField): array
    {
        $seen = [];
        $unique = [];
        
        foreach ($records as $record) {
            if (!isset($record[$keyField])) {
                continue;
            }
            
            $key = $record[$keyField];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $record;
            }
        }
        
        return $unique;
    }
}
