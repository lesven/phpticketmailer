<?php

namespace App\Service;

use App\Dto\TemplateResolutionResult;
use App\Entity\EmailTemplate;
use App\Repository\EmailTemplateRepository;

/**
 * Service für die Verwaltung von E-Mail-Templates mit Gültigkeitsdatum.
 *
 * Stellt CRUD-Operationen bereit und wählt beim E-Mail-Versand
 * automatisch das passende Template anhand des Ticket-Erstelldatums.
 * Bietet außerdem eine zentrale Methode zum Ersetzen von Platzhaltern.
 */
class TemplateService
{
    private const GERMAN_MONTHS = [
        1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
        5 => 'Mai',    6 => 'Juni',     7 => 'Juli',  8 => 'August',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
    ];

    public function __construct(
        private readonly EmailTemplateRepository $repository,
        private readonly DateParserService $dateParser,
        private readonly string $projectDir
    ) {
    }

    /**
     * Alle Templates sortiert nach validFrom (neueste zuerst).
     *
     * @return EmailTemplate[]
     */
    public function getAllTemplates(): array
    {
        return $this->repository->findAllOrderedByValidFrom();
    }

    /**
     * Lädt ein einzelnes Template anhand seiner ID.
     *
     * @param int $id Die Template-ID
     * @return EmailTemplate|null Das Template oder null wenn nicht gefunden
     */
    public function getTemplate(int $id): ?EmailTemplate
    {
        return $this->repository->find($id);
    }

    /**
     * Erstellt ein neues Template mit Default-Inhalt.
     */
    public function createTemplate(string $name, \DateTimeInterface $validFrom): EmailTemplate
    {
        $template = new EmailTemplate();
        $template->setName($name);
        $template->setValidFrom($validFrom);
        $template->setContent($this->getDefaultContent());
        $this->repository->save($template);

        return $template;
    }

    /**
     * Speichert Änderungen an einem bestehenden Template.
     *
     * @param EmailTemplate $template Das zu speichernde Template
     */
    public function saveTemplate(EmailTemplate $template): void
    {
        $this->repository->save($template);
    }

    /**
     * Löscht ein Template aus der Datenbank.
     *
     * @param EmailTemplate $template Das zu löschende Template
     */
    public function deleteTemplate(EmailTemplate $template): void
    {
        $this->repository->remove($template);
    }

    /**
     * Findet das passende Template für ein Ticket-Erstelldatum.
     *
     * @deprecated Verwende resolveTemplateForTicketDate() für typisiertes Ergebnis
     */
    public function getTemplateContentForTicketDate(?string $ticketCreated): string
    {
        return $this->resolveTemplateForTicketDate($ticketCreated)->content;
    }

    /**
     * Gibt das passende Template inkl. Debug-Informationen zurück.
     *
     * Es wird das Template gewählt, dessen validFrom <= dem Ticket-Erstelldatum
     * ist (das Template, das zum Zeitpunkt der Ticket-Erstellung aktiv war).
     * Wenn kein created-Datum vorhanden ist, wird das neueste Template verwendet.
     * Wenn gar kein Template existiert, wird das Dateisystem-Fallback oder ein
     * Inline-Default zurückgegeben.
     */
    public function resolveTemplateForTicketDate(?string $ticketCreated): TemplateResolutionResult
    {
        $allTemplatesDebug = $this->buildTemplateDebugList();

        $parsedDate = null;
        $selectionMethod = null;
        $template = null;

        if ($ticketCreated !== null && trim($ticketCreated) !== '') {
            $parsedDate = $this->dateParser->parse($ticketCreated);

            if ($parsedDate !== null) {
                $template = $this->repository->findActiveTemplateForDate($parsedDate);
                if ($template !== null) {
                    $selectionMethod = 'date_match';
                }
            } else {
                $selectionMethod = 'parse_failed';
            }
        } else {
            $selectionMethod = 'no_created_date';
        }

        // Fallback: neuestes Template
        if ($template === null) {
            $template = $this->repository->findLatestTemplate();
            if ($template !== null) {
                $selectionMethod = $selectionMethod === 'parse_failed'
                    ? 'parse_failed → fallback_latest'
                    : ($selectionMethod ?? 'no_created_date') . ' → fallback_latest';
            }
        }

        // Fallback: Dateisystem-Template
        if ($template === null) {
            return new TemplateResolutionResult(
                content: $this->getFilesystemTemplate(),
                inputCreated: $ticketCreated,
                parsedDate: $parsedDate?->format('Y-m-d'),
                selectionMethod: ($selectionMethod ?? '') . ' → fallback_filesystem',
                allTemplates: $allTemplatesDebug,
            );
        }

        return new TemplateResolutionResult(
            content: $template->getContent(),
            inputCreated: $ticketCreated,
            parsedDate: $parsedDate?->format('Y-m-d'),
            selectedTemplateName: $template->getName(),
            selectedTemplateValidFrom: $template->getValidFrom()?->format('Y-m-d'),
            selectionMethod: $selectionMethod,
            allTemplates: $allTemplatesDebug,
        );
    }

    /**
     * Ersetzt Platzhalter im Template-Inhalt durch konkrete Werte.
     *
     * Zentralisierte Methode für Placeholder-Ersetzung, die sowohl vom
     * EmailService als auch vom TemplateController verwendet wird.
     *
     * @param string $template Der Template-Inhalt mit Platzhaltern
     * @param array<string, string> $variables Assoziatives Array (Placeholder-Name => Wert)
     *        Unterstützte Keys: ticketId, ticketName, username, ticketLink, dueDate, created
     * @return string Der Template-Inhalt mit eingesetzten Werten
     */
    public function replacePlaceholders(string $template, array $variables): string
    {
        $defaults = [
            'ticketId'   => 'TICKET-ID',
            'ticketName' => 'Ticket-Name',
            'username'   => 'Benutzername',
            'ticketLink' => 'https://www.ticket.de/ticket-id',
            'dueDate'    => $this->formatGermanDueDate(),
            'created'    => '',
        ];

        $merged = array_merge($defaults, $variables);

        $placeholders = [];
        foreach ($merged as $key => $value) {
            $placeholders['{{' . $key . '}}'] = (string) $value;
        }

        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }

    /**
     * Formatiert ein Fälligkeitsdatum (heute + 7 Tage) auf Deutsch.
     */
    public function formatGermanDueDate(?\DateTimeInterface $from = null): string
    {
        $dueDate = $from !== null
            ? \DateTime::createFromInterface($from)
            : new \DateTime();
        $dueDate->modify('+7 days');

        return $dueDate->format('d') . '. '
            . self::GERMAN_MONTHS[(int) $dueDate->format('n')]
            . ' ' . $dueDate->format('Y');
    }

    /**
     * Erzeugt Beispieldaten für die Template-Vorschau im Editor.
     *
     * @return array<string, string> Platzhalter-Schlüssel => Beispielwerte
     */
    public function getPreviewData(): array
    {
        return [
            'ticketId'   => 'TICKET-12345',
            'ticketName' => 'Beispiel Support-Anfrage',
            'username'   => 'max.mustermann',
            'ticketLink' => 'https://www.ticket.de/TICKET-12345',
            'dueDate'    => $this->formatGermanDueDate(),
        ];
    }

    // ── Private Hilfsmethoden ──────────────────────────────────

    /**
     * Sammelt Debug-Übersicht aller Templates.
     *
     * @return array<int, array{id: int|null, name: string, validFrom: string|null}>
     */
    private function buildTemplateDebugList(): array
    {
        $list = [];
        foreach ($this->repository->findAllOrderedByValidFrom() as $t) {
            $list[] = [
                'id' => $t->getId(),
                'name' => $t->getName(),
                'validFrom' => $t->getValidFrom()?->format('Y-m-d'),
            ];
        }

        return $list;
    }

    /**
     * Lädt Template vom Dateisystem (Legacy-Fallback).
     */
    private function getFilesystemTemplate(): string
    {
        $htmlPath = $this->projectDir . '/templates/emails/email_template.html';
        if (file_exists($htmlPath)) {
            return file_get_contents($htmlPath);
        }

        $txtPath = $this->projectDir . '/templates/emails/email_template.txt';
        if (file_exists($txtPath)) {
            return file_get_contents($txtPath);
        }

        return $this->getDefaultContent();
    }

    /**
     * Standard-Template-Inhalt.
     */
    public function getDefaultContent(): string
    {
        return <<<'EOT'
<p>Sehr geehrte(r) {{username}},</p>

<p>wir möchten gerne Ihre Meinung zu dem kürzlich bearbeiteten Ticket erfahren:</p>

<p><strong>Ticket-Nr:</strong> {{ticketId}}<br>
<strong>Betreff:</strong> {{ticketName}}</p>

<p>Um das Ticket anzusehen und Feedback zu geben, <a href="{{ticketLink}}">klicken Sie bitte hier</a>.</p>

<p>Bitte beantworten Sie die Umfrage bis zum {{dueDate}}.</p>

<p>Vielen Dank für Ihre Rückmeldung!</p>

<p>Mit freundlichen Grüßen<br>
Ihr Support-Team</p>
EOT;
    }
}
