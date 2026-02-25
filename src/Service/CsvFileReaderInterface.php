<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Interface für das Lesen und Validieren von CSV-Dateien
 *
 * Definiert den Vertrag für CSV-Datei-Operationen: Öffnen, Header-Lesen,
 * Spalten-Validierung, Zeilen-Verarbeitung und Schließen.
 */
interface CsvFileReaderInterface
{
    /**
     * Öffnet eine CSV-Datei und gibt den Handle zurück
     *
     * @param UploadedFile|string $file Die hochgeladene CSV-Datei oder ein Dateipfad
     * @return resource Der Datei-Handle
     * @throws \Exception Wenn die Datei nicht geöffnet werden kann
     */
    public function openCsvFile(UploadedFile|string $file);

    /**
     * Liest und validiert den CSV-Header
     *
     * @param resource $handle Der Datei-Handle
     * @return array Der CSV-Header als Array
     * @throws \Exception Wenn der Header nicht gelesen werden kann
     */
    public function readHeader($handle): array;

    /**
     * Validiert, ob alle erforderlichen Spalten im Header vorhanden sind
     *
     * @param array $header Der Header als Array
     * @param array $requiredColumns Die Namen der erforderlichen Spalten
     * @return array<string, int> Die Indizes der erforderlichen Spalten
     * @throws \Exception Wenn nicht alle erforderlichen Spalten vorhanden sind
     */
    public function validateRequiredColumns(array $header, array $requiredColumns): array;

    /**
     * Liest alle Zeilen der CSV-Datei und verarbeitet sie mit einer Callback-Funktion
     *
     * @param resource $handle Der Datei-Handle
     * @param callable $rowProcessor Funktion zur Verarbeitung jeder Zeile
     */
    public function processRows($handle, callable $rowProcessor): void;

    /**
     * Schließt einen geöffneten Datei-Handle sicher
     *
     * @param resource|null $handle Der zu schließende Datei-Handle
     */
    public function closeHandle($handle): void;
}
