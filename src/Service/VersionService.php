<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Service zur Verwaltung von Software-Versionsinformationen
 */
class VersionService
{
    private $projectDir;
    private $version = null;
    private $updateTimestamp = null;
    
    /**
     * Konstruktor
     *
     * @param ParameterBagInterface $parameterBag
     */
    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->projectDir = $parameterBag->get('kernel.project_dir');
        $this->loadVersionInfo();
    }
    
    /**
     * Lädt die Versionsinformationen aus der VERSION-Datei
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
     * @return string|null
     */
    public function getVersion(): ?string
    {
        return $this->version;
    }
    
    /**
     * Gibt den Zeitstempel des letzten Updates zurück
     *
     * @return string|null
     */
    public function getUpdateTimestamp(): ?string
    {
        return $this->updateTimestamp;
    }
    
    /**
     * Gibt eine formatierte Versionszeichenkette zurück
     * 
     * @return string
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
     * @param string $version Neue Versionsnummer (optional)
     * @param bool $updateTimestamp Ob der Zeitstempel aktualisiert werden soll
     * @return bool
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
