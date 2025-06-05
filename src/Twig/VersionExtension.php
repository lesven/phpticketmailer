<?php

namespace App\Twig;

use App\Service\VersionService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig-Erweiterung für Versionsinformationen
 */
class VersionExtension extends AbstractExtension
{
    private $versionService;
    
    /**
     * Konstruktor
     *
     * @param VersionService $versionService
     */
    public function __construct(VersionService $versionService)
    {
        $this->versionService = $versionService;
    }
    
    /**
     * Registriert die verfügbaren Twig-Funktionen
     *
     * @return array
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('app_version', [$this, 'getAppVersion']),
            new TwigFunction('app_update_timestamp', [$this, 'getAppUpdateTimestamp']),
            new TwigFunction('app_version_string', [$this, 'getAppVersionString']),
        ];
    }
    
    /**
     * Gibt die Anwendungsversion zurück
     *
     * @return string|null
     */
    public function getAppVersion(): ?string
    {
        return $this->versionService->getVersion();
    }
    
    /**
     * Gibt den Zeitstempel des letzten Updates zurück
     *
     * @return string|null
     */
    public function getAppUpdateTimestamp(): ?string
    {
        return $this->versionService->getUpdateTimestamp();
    }
    
    /**
     * Gibt eine formatierte Versionszeichenkette zurück
     *
     * @return string
     */
    public function getAppVersionString(): string
    {
        return $this->versionService->getFormattedVersionString();
    }
}
