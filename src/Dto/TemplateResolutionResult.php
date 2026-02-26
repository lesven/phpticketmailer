<?php

namespace App\Dto;

/**
 * Typisiertes Ergebnis der Template-Auflösung für ein Ticket-Datum.
 *
 * Enthält den Template-Inhalt sowie Debug-Informationen über die
 * Auswahl-Methode (Datum-Match, Fallback, Dateisystem etc.).
 */
final readonly class TemplateResolutionResult
{
    /**
     * @param string $content Der aufgelöste Template-Inhalt
     * @param string|null $inputCreated Der ursprüngliche Ticket-Erstelldatums-String
     * @param string|null $parsedDate Das geparste Datum (Y-m-d) oder null
     * @param string|null $selectedTemplateName Name des ausgewählten Templates
     * @param string|null $selectedTemplateValidFrom ValidFrom des gewählten Templates (Y-m-d)
     * @param string|null $selectionMethod Beschreibung der Auswahl-Methode
     * @param array<int, array{id: int|null, name: string, validFrom: string|null}> $allTemplates Übersicht aller Templates
     */
    public function __construct(
        public string $content,
        public ?string $inputCreated = null,
        public ?string $parsedDate = null,
        public ?string $selectedTemplateName = null,
        public ?string $selectedTemplateValidFrom = null,
        public ?string $selectionMethod = null,
        public array $allTemplates = [],
    ) {
    }

    /**
     * Gibt die Debug-Informationen als Array zurück (Backward-Compatibility).
     *
     * @return array<string, mixed>
     */
    public function toDebugArray(): array
    {
        return [
            'inputCreated' => $this->inputCreated,
            'parsedDate' => $this->parsedDate,
            'selectedTemplateName' => $this->selectedTemplateName,
            'selectedTemplateValidFrom' => $this->selectedTemplateValidFrom,
            'selectionMethod' => $this->selectionMethod,
            'allTemplates' => $this->allTemplates,
        ];
    }
}
