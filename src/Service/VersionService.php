<?php
declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Service zur Verwaltung von Software-Versionsinformationen
 *
 * Diese Klasse verwaltet die Versionsinformationen der Anwendung durch
 * Lesen und Schreiben einer VERSION-Datei im Projekt-Root-Verzeichnis.
 */
final class VersionService
{
    /**
     * Das Projektverzeichnis
     */
    private string $projectDir;
    
    /**
     * Die aktuelle Versionsnummer
     */
    private ?string $version = null;
    
    /**
     * Der Zeitstempel des letzten Updates
     */
    private ?string $updateTimestamp = null;
    
    /**
     * Konstruktor
     *
     * @param ParameterBagInterface $parameterBag Der Parameter-Bag für Zugriff auf Konfigurationswerte
     */
    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->projectDir = $parameterBag->get('kernel.project_dir');
        $this->loadVersionInfo();
    }
    
    /**
     * Lädt die Versionsinformationen aus der VERSION-Datei
     *
     * Diese Methode liest die VERSION-Datei und parst den Inhalt,
     * der im Format "Version|Zeitstempel" erwartet wird.
     *
     * @return void
     */
    private function loadVersionInfo(): void
    {
        $versionFile = $this->projectDir . '/VERSION';
        
        if (file_exists($versionFile)) {
            $versionData = file_get_contents($versionFile);
            $parts = explode('|', $versionData);
            
            if (isset($parts[0])) {
                $this->version = trim($parts[0]);
            }
            
            if (isset($parts[1])) {
                $this->updateTimestamp = trim($parts[1]);
            }
        }
    }
    
    /**
     * Gibt die aktuelle Versionsnummer zurück
     *
     * @return string|null Die Versionsnummer oder null wenn nicht verfügbar
     */
    public function getVersion(): ?string
    {
        return $this->version;
    }
    
    /**
     * Gibt den Zeitstempel des letzten Updates zurück
     *
     * @return string|null Der Update-Zeitstempel oder null wenn nicht verfügbar
     */
    public function getUpdateTimestamp(): ?string
    {
        return $this->updateTimestamp;
    }
    
    /**
     * Gibt eine formatierte Versionszeichenkette zurück
     *
     * Diese Methode erstellt eine benutzerfreundliche Darstellung der
     * Versionsinformationen basierend auf den verfügbaren Daten.
     *
     * @return string Die formatierte Versionszeichenkette
     */
    public function getFormattedVersionString(): string
    {
        if (!$this->version && !$this->updateTimestamp) {
            return 'Version nicht verfügbar';
        }
        
        if ($this->version && $this->updateTimestamp) {
            return sprintf('Version %s (Stand: %s)', $this->version, $this->updateTimestamp);
        }
        
        if ($this->version) {
            return sprintf('Version %s', $this->version);
        }
        
        return sprintf('Stand: %s', $this->updateTimestamp);
    }
    
    /**
     * Aktualisiert die Versionsdaten in der VERSION-Datei
     *
     * Diese Methode aktualisiert die Versionsinformationen und schreibt sie
     * in die VERSION-Datei. Optional kann nur der Zeitstempel aktualisiert werden.
     *
     * @param string|null $version Neue Versionsnummer (optional)
     * @param bool $updateTimestamp Ob der Zeitstempel aktualisiert werden soll
     * @return bool True bei erfolgreichem Schreiben, false bei Fehlern
     */
    public function updateVersionInfo(?string $version = null, bool $updateTimestamp = true): bool
    {
        if ($version) {
            $this->version = $version;
        }
        
        if ($updateTimestamp) {
            $this->updateTimestamp = date('Y-m-d H:i:s');
        }
        
        $versionData = $this->version . '|' . $this->updateTimestamp;
        $versionFile = $this->projectDir . '/VERSION';
        
        if (file_put_contents($versionFile, $versionData) !== false) {
            return true;
        }
        
        return false;
    }
}
