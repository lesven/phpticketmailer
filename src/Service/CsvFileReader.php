<?php
/**
 * CsvFileReader.php
 * 
 * Diese Klasse ist verantwortlich für das grundlegende Lesen und Validieren 
 * von CSV-Dateien. Sie stellt Methoden bereit, um Header zu validieren und
 * Zeilendaten zu extrahieren.
 * 
 * @package App\Service
 */

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class CsvFileReader
{
    /**
     * @var string Das Standard-Trennzeichen für die CSV-Datei
     */
    private string $delimiter;
    
    /**
     * @var int Die maximale Länge einer CSV-Zeile
     */    private int $maxLineLength;
    
    /**
     * Konstruktor zum Konfigurieren des CSV-Readers
     * 
     * @param string $delimiter Das CSV-Trennzeichen
     * @param int $maxLineLength Die maximale Zeilenlänge
     */
    public function __construct(string $delimiter = ',', int $maxLineLength = 1000)
    {
        $this->delimiter = $delimiter;
        $this->maxLineLength = $maxLineLength;
    }
    
    /**
     * Öffnet eine CSV-Datei und gibt den Handle zurück
     * 
     * @param UploadedFile|string $file Die hochgeladene CSV-Datei oder ein Dateipfad
     * @return resource Der Datei-Handle
     * @throws \Exception Wenn die Datei nicht geöffnet werden kann
     */
    public function openCsvFile($file)
    {
        $path = $file instanceof UploadedFile ? $file->getPathname() : $file;
        $handle = @fopen($path, 'r');
        
        if ($handle === false) {
            throw new \Exception('CSV-Datei konnte nicht geöffnet werden');
        }
        
        return $handle;
    }
    
    /**
     * Liest und validiert den CSV-Header
     * 
     * @param resource $handle Der Datei-Handle
     * @return array Der CSV-Header als Array
     * @throws \Exception Wenn der Header nicht gelesen werden kann
     */
    public function readHeader($handle): array
    {
        $header = fgetcsv($handle, $this->maxLineLength, $this->delimiter);
        if ($header === false) {
            throw new \Exception('CSV-Header konnte nicht gelesen werden');
        }
        return $header;
    }
    
    /**
     * Validiert, ob alle erforderlichen Spalten im Header vorhanden sind
     * 
     * @param array $header Der Header als Array
     * @param array $requiredColumns Die Namen der erforderlichen Spalten
     * @return array<string, int> Die Indizes der erforderlichen Spalten
     * @throws \Exception Wenn nicht alle erforderlichen Spalten vorhanden sind
     */
    public function validateRequiredColumns(array $header, array $requiredColumns): array
    {
        $indices = [];
        $missingColumns = [];
        
        foreach ($requiredColumns as $columnName) {
            $index = array_search($columnName, $header);
            if ($index === false) {
                $missingColumns[] = $columnName;
            } else {
                $indices[$columnName] = $index;
            }
        }
        
        if (!empty($missingColumns)) {
            throw new \Exception(sprintf(
                'CSV-Datei enthält nicht alle erforderlichen Spalten: %s',
                implode(', ', $missingColumns)
            ));
        }
        
        return $indices;
    }
    
    /**
     * Liest alle Zeilen der CSV-Datei und verarbeitet sie mit einer Callback-Funktion
     * 
     * @param resource $handle Der Datei-Handle
     * @param callable $rowProcessor Funktion zur Verarbeitung jeder Zeile
     * @return void
     */
    public function processRows($handle, callable $rowProcessor): void
    {
        $rowNumber = 1; // Header ist Zeile 1
        
        while (($row = fgetcsv($handle, $this->maxLineLength, $this->delimiter)) !== false) {
            $rowNumber++;
            $rowProcessor($row, $rowNumber);
        }
    }
    
    
    /**
     * Schließt einen geöffneten Datei-Handle sicher
     * 
     * @param resource|null $handle Der zu schließende Datei-Handle
     * @return void
     */
    public function closeHandle($handle): void
    {
        if ($handle !== null && $handle !== false) {
            fclose($handle);
        }
    }
}