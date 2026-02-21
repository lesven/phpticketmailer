<?php

namespace App\Service\Email;

use App\ValueObject\TicketData;
use App\Service\TemplateService;

/**
 * Verantwortlich für Template-Auflösung und Placeholder-Ersetzung im E-Mail-Body.
 *
 * Kapselt die gesamte Logik zur Aufbereitung des E-Mail-Inhalts:
 * - Globales Fallback-Template vom Dateisystem
 * - Template-Auflösung per Ticket-Datum (delegiert an TemplateService)
 * - Placeholder-Ersetzung (ticketId, ticketLink, username, dueDate usw.)
 * - Testmodus-Header
 */
class EmailContentRenderer
{
    /** @var array<string, array<string, mixed>> */
    private array $templateDebugInfo = [];

    public function __construct(
        private readonly TemplateService $templateService,
        private readonly string $projectDir,
    ) {
    }

    /**
     * Lädt das globale Fallback-Template vom Dateisystem.
     */
    public function getGlobalTemplate(): string
    {
        $htmlPath = $this->projectDir . '/templates/emails/email_template.html';
        if (file_exists($htmlPath)) {
            return file_get_contents($htmlPath);
        }

        $templatePath = $this->projectDir . '/templates/emails/email_template.txt';

        if (!file_exists($templatePath)) {
            return "<p>Sehr geehrte(r) {{username}},</p>

<p>wir möchten gerne Ihre Meinung zu dem kürzlich bearbeiteten Ticket erfahren:</p>

<p><strong>Ticket-Nr:</strong> {{ticketId}}<br>
<strong>Betreff:</strong> {{ticketName}}</p>

<p>Um das Ticket anzusehen und Feedback zu geben, <a href=\"{{ticketLink}}\">klicken Sie bitte hier</a>.</p>

<p>Bitte beantworten Sie die Umfrage bis zum {{dueDate}}.</p>

<p>Vielen Dank für Ihre Rückmeldung!</p>

<p>Mit freundlichen Grüßen<br>
Ihr Support-Team</p>";
        }

        return file_get_contents($templatePath);
    }

    /**
     * Wählt das passende Template für ein Ticket und speichert Debug-Infos.
     */
    public function resolveTemplateForTicket(TicketData $ticketObj, string $globalFallback): string
    {
        $ticketIdStr = (string) $ticketObj->ticketId;
        $resolved = $this->templateService->resolveTemplateForTicketDate($ticketObj->created);
        $templateContent = $resolved['content'] ?? '';
        $this->templateDebugInfo[$ticketIdStr] = $resolved['debug'] ?? [];

        if ($templateContent === '' || trim($templateContent) === '') {
            $templateContent = $globalFallback;
            $selectionMethod = $this->templateDebugInfo[$ticketIdStr]['selectionMethod'] ?? '';
            $this->templateDebugInfo[$ticketIdStr]['selectionMethod'] = $selectionMethod . ' → fallback_global';
        }

        return $templateContent;
    }

    /**
     * Ersetzt Platzhalter im Template und fügt ggf. Testmodus-Header hinzu.
     *
     * @param object $user Entity mit getEmail()-Methode
     */
    public function render(
        string $template,
        TicketData $ticketData,
        object $user,
        string $ticketBaseUrl,
        bool $testMode
    ): string {
        $ticketLink = rtrim($ticketBaseUrl, '/') . '/' . (string) $ticketData->ticketId;
        $emailBody = $template;
        $emailBody = str_replace('{{ticketId}}', (string) $ticketData->ticketId, $emailBody);
        $emailBody = str_replace('{{ticketLink}}', $ticketLink, $emailBody);
        $emailBody = str_replace('{{ticketName}}', (string) $ticketData->ticketName, $emailBody);
        $emailBody = str_replace('{{username}}', (string) $ticketData->username, $emailBody);
        $emailBody = str_replace('{{created}}', $ticketData->created ?? '', $emailBody);

        $dueDate = new \DateTime();
        $dueDate->modify('+7 days');
        $germanMonths = [
            1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni',
            7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'
        ];
        $formattedDueDate = $dueDate->format('d') . '. ' . $germanMonths[(int)$dueDate->format('n')] . ' ' . $dueDate->format('Y');
        $emailBody = str_replace('{{dueDate}}', $formattedDueDate, $emailBody);

        if ($testMode) {
            $emailBody = "*** TESTMODUS - E-Mail wäre an {$user->getEmail()} gegangen ***\n\n" . $emailBody;
        }

        return $emailBody;
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
}
