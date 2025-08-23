<?php

namespace App\Service;

use App\Entity\User;

/**
 * Helper-Klasse für CSV-spezifische Operationen rund um Benutzer.
 *
 * Verantwortlich für das Mapping von CSV-Zeilen auf interne Strukturen
 * sowie das Formatieren von User-Entitäten in CSV-Zeilen.
 */
class UserCsvHelper
{
    /**
     * Mappt eine CSV-Zeile auf das interne Benutzer-Daten-Array.
     *
     * Erwartet ein assoziatives `$columnIndices`-Array mit den Schlüsseln
     * 'username' und 'email', die jeweils den Index in der CSV-Zeile angeben.
     *
     * Beispiel:
     *   $columnIndices = ['username' => 0, 'email' => 1];
     *
     * @param array $row Die rohe CSV-Zeile (numerisches Array)
     * @param array $columnIndices ['username' => int, 'email' => int]
     * @return array{username: string, email: string}
     * @throws \InvalidArgumentException Wenn erforderliche Indizes fehlen oder
     *                                    die Zeile die erwarteten Spalten nicht enthält.
     */
    public function mapRowToUserData(array $row, array $columnIndices): array
    {
        if (!isset($columnIndices['username']) || !isset($columnIndices['email'])) {
            throw new \InvalidArgumentException('Missing column indices for username or email');
        }

        $usernameIndex = $columnIndices['username'];
        $emailIndex = $columnIndices['email'];

        if (!array_key_exists($usernameIndex, $row) || !array_key_exists($emailIndex, $row)) {
            throw new \InvalidArgumentException('Row does not contain the required columns');
        }

        return [
            'username' => (string) $row[$usernameIndex],
            'email' => (string) $row[$emailIndex],
        ];
    }

    /**
    * Formatiert einen User als CSV-Zeile.
    *
    * Das Ergebnis enthält die Felder ID, username und email, wobei
    * Felder für den CSV-Export gemäß RFC durch doppelte Anführungszeichen
    * escaped werden. Die Zeile endet mit einem Newline ("\n").
    *
    * @param User $user
    * @return string CSV-zeile inklusive abschließendem Newline
    */
    public function formatUserAsCsvLine(User $user): string
    {
        return sprintf(
            "%d,%s,%s\n",
            $user->getId(),
            $this->escapeCsvField($user->getUsername()),
            $this->escapeCsvField($user->getEmail())
        );
    }

    /**
    * Escaped ein CSV-Feld für den Export.
    *
    * Ersetzt enthaltene Anführungszeichen durch doppelte Anführungszeichen
    * und umschließt das Feld mit Anführungszeichen.
    *
    * Beispiel: a"b  -> "a""b"
    *
    * @param string $field Rohes Feld
    * @return string Escaped Feld, inklusive umschließender Anführungszeichen
    */
    public function escapeCsvField(string $field): string
    {
        return '"' . str_replace('"', '""', $field) . '"';
    }
}
