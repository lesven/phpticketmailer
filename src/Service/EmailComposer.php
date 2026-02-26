<?php

namespace App\Service;

use App\Dto\TemplateResolutionResult;
use App\Entity\User;
use App\ValueObject\TicketData;

/**
 * Verantwortlich für Template-Auflösung, Placeholder-Ersetzung
 * und das Sammeln von Template-Debug-Informationen.
 *
 * Kapselt die gesamte Content-Composition-Logik für E-Mails.
 */
class EmailComposer
{
    /** @var array<string, array<string, mixed>> */
    private array $templateDebugInfo = [];

    public function __construct(
        private readonly TemplateService $templateService,
    ) {
    }

    /**
     * Wählt das Template für ein Ticket aus und speichert Debug-Infos.
     */
    public function resolveTemplateContent(TicketData $ticketObj, string $globalFallback): string
    {
        $ticketIdStr = (string) $ticketObj->ticketId;
        $resolved = $this->templateService->resolveTemplateForTicketDate($ticketObj->created);
        $templateContent = $resolved->content;
        $this->templateDebugInfo[$ticketIdStr] = $resolved->toDebugArray();

        if ($templateContent === '' || trim($templateContent) === '') {
            $templateContent = $globalFallback;
            $selectionMethod = $this->templateDebugInfo[$ticketIdStr]['selectionMethod'] ?? '';
            $this->templateDebugInfo[$ticketIdStr]['selectionMethod'] = $selectionMethod . ' → fallback_global';
        }

        return $templateContent;
    }

    /**
     * Bereitet den E-Mail-Inhalt vor, indem Platzhalter ersetzt werden.
     *
     * Delegiert die Placeholder-Ersetzung an TemplateService.
     */
    public function prepareEmailContent(
        string $template,
        TicketData $ticketData,
        ?User $user,
        string $ticketBaseUrl,
        bool $testMode,
    ): string {
        $ticketLink = rtrim($ticketBaseUrl, '/') . '/' . (string) $ticketData->ticketId;

        $emailBody = $this->templateService->replacePlaceholders($template, [
            'ticketId'   => (string) $ticketData->ticketId,
            'ticketName' => (string) $ticketData->ticketName,
            'username'   => (string) $ticketData->username,
            'ticketLink' => $ticketLink,
            'created'    => $ticketData->created ?? '',
        ]);

        if ($testMode && $user !== null) {
            $emailBody = "*** TESTMODUS - E-Mail wäre an {$user->getEmail()} gegangen ***\n\n" . $emailBody;
        }

        return $emailBody;
    }

    /**
     * Gibt den Standard-Template-Inhalt zurück.
     */
    public function getDefaultContent(): string
    {
        return $this->templateService->getDefaultContent();
    }

    /**
     * Gibt die Template-Debug-Infos pro Ticket zurück.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getTemplateDebugInfo(): array
    {
        return $this->templateDebugInfo;
    }

    /**
     * Setzt die Template-Debug-Infos zurück (z.B. vor einem neuen Batch).
     */
    public function resetTemplateDebugInfo(): void
    {
        $this->templateDebugInfo = [];
    }
}
