<?php

namespace App\Service;

/**
 * Service zum Parsen von Datumsangaben aus verschiedenen Formaten.
 *
 * Unterstützt gängige Datumsformate (ISO, deutsch, US) mit und ohne
 * Uhrzeitangabe, sowie zwei- und vierstellige Jahresangaben.
 * Als letzten Fallback wird strtotime() verwendet.
 *
 * Der '!'-Prefix in den Formaten setzt alle nicht angegebenen Felder
 * (insb. die Uhrzeit) auf 0, damit DATE-Vergleiche in der DB
 * nicht durch die aktuelle Uhrzeit verfälscht werden.
 */
class DateParserService
{
    /**
     * Unterstützte Datumsformate.
     * Reihenfolge: erst vierstellige Jahre, dann zweistellige.
     */
    private const FORMATS = [
        '!Y-m-d H:i:s',
        '!Y-m-d H:i',
        '!Y-m-d',
        '!d.m.Y H:i:s',
        '!d.m.Y H:i',
        '!d.m.Y',
        '!d/m/Y H:i:s',
        '!d/m/Y H:i',
        '!d/m/Y',
        '!m/d/Y',
        // Zweistellige Jahresangaben (z.B. 18/12/25)
        '!d/m/y H:i:s',
        '!d/m/y H:i',
        '!d/m/y',
        '!d.m.y H:i:s',
        '!d.m.y H:i',
        '!d.m.y',
        '!y-m-d H:i:s',
        '!y-m-d H:i',
        '!y-m-d',
    ];

    private const MIN_YEAR = 1970;
    private const MAX_YEAR = 2099;

    /**
     * Versucht ein Datum aus verschiedenen Formaten zu parsen.
     *
     * @param string $dateString Der zu parsende Datums-String
     * @return \DateTimeInterface|null Das geparste Datum oder null bei ungültiger Eingabe
     */
    public function parse(string $dateString): ?\DateTimeInterface
    {
        $input = trim($dateString);
        if ($input === '') {
            return null;
        }

        $date = $this->tryFormats($input);
        if ($date !== null) {
            return $date;
        }

        return $this->tryStrtotime($input);
    }

    /**
     * Versucht den Input gegen alle bekannten Formate zu parsen.
     */
    private function tryFormats(string $input): ?\DateTimeInterface
    {
        foreach (self::FORMATS as $format) {
            $date = \DateTime::createFromFormat($format, $input);
            if ($date === false) {
                continue;
            }

            if ($this->hasParseWarnings()) {
                continue;
            }

            if (!$this->isPlausibleYear($date)) {
                continue;
            }

            return $date;
        }

        return null;
    }

    /**
     * Prüft ob beim letzten DateTime::createFromFormat Warnungen aufgetreten sind.
     */
    private function hasParseWarnings(): bool
    {
        $errors = \DateTime::getLastErrors();

        return $errors !== false
            && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);
    }

    /**
     * Prüft ob die Jahreszahl im plausiblen Bereich liegt.
     *
     * PHP akzeptiert z.B. "26" als vierstelliges Jahr 0026 bei Format Y.
     * Nur Jahre zwischen 1970 und 2099 werden akzeptiert.
     */
    private function isPlausibleYear(\DateTimeInterface $date): bool
    {
        $year = (int) $date->format('Y');

        return $year >= self::MIN_YEAR && $year <= self::MAX_YEAR;
    }

    /**
     * Letzter Versuch via strtotime() als Fallback.
     */
    private function tryStrtotime(string $input): ?\DateTimeInterface
    {
        $ts = strtotime($input);
        if ($ts === false) {
            return null;
        }

        $date = new \DateTime();
        $date->setTimestamp($ts);
        $date->setTime(0, 0, 0);

        return $date;
    }
}
