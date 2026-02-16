<?php

namespace App\Service;

use App\Entity\EmailTemplate;
use App\Repository\EmailTemplateRepository;

/**
 * Service für die Verwaltung von E-Mail-Templates mit Gültigkeitsdatum.
 *
 * Stellt CRUD-Operationen bereit und wählt beim E-Mail-Versand
 * automatisch das passende Template anhand des Ticket-Erstelldatums.
 */
class TemplateService
{
    public function __construct(
        private readonly EmailTemplateRepository $repository,
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
     * Es wird das Template gewählt, dessen validFrom >= dem Ticket-Erstelldatum
     * ist (das nächste gültige Template ab dem Ticket-Datum).
     * Beispiel: Templates mit validFrom 03.02. und 13.02., Ticket vom 10.02.
     * → Template vom 13.02. wird gewählt (nächstes ab Ticket-Datum).
     *
     * Wenn kein created-Datum vorhanden ist, wird das neueste Template
     * verwendet. Wenn gar kein Template existiert, wird das Dateisystem-
     * Fallback oder ein Inline-Default zurückgegeben.
     */
    public function getTemplateContentForTicketDate(?string $ticketCreated): string
    {
        return $this->resolveTemplateForTicketDate($ticketCreated)['content'];
    }

    /**
     * Gibt das passende Template inkl. Debug-Informationen zurück.
     *
     * @return array{content: string, debug: array<string, mixed>}
     */
    public function resolveTemplateForTicketDate(?string $ticketCreated): array
    {
        $debug = [
            'inputCreated' => $ticketCreated,
            'parsedDate' => null,
            'selectedTemplateName' => null,
            'selectedTemplateValidFrom' => null,
            'selectionMethod' => null,
            'allTemplates' => [],
        ];

        // Alle Templates für Debug-Übersicht sammeln
        $allTemplates = $this->repository->findAllOrderedByValidFrom();
        foreach ($allTemplates as $t) {
            $debug['allTemplates'][] = [
                'id' => $t->getId(),
                'name' => $t->getName(),
                'validFrom' => $t->getValidFrom()?->format('Y-m-d'),
            ];
        }

        $template = null;

        if ($ticketCreated !== null && trim($ticketCreated) !== '') {
            $date = $this->parseDate($ticketCreated);
            $debug['parsedDate'] = $date?->format('Y-m-d');

            if ($date !== null) {
                $template = $this->repository->findActiveTemplateForDate($date);
                if ($template !== null) {
                    $debug['selectionMethod'] = 'date_match';
                }
            } else {
                $debug['selectionMethod'] = 'parse_failed';
            }
        } else {
            $debug['selectionMethod'] = 'no_created_date';
        }

        // Fallback: neuestes Template
        if ($template === null) {
            $template = $this->repository->findLatestTemplate();
            if ($template !== null && $debug['selectionMethod'] !== 'parse_failed') {
                $debug['selectionMethod'] = ($debug['selectionMethod'] ?? 'no_created_date') . ' → fallback_latest';
            } elseif ($template !== null) {
                $debug['selectionMethod'] = 'parse_failed → fallback_latest';
            }
        }

        // Fallback: Dateisystem-Template
        if ($template === null) {
            $debug['selectionMethod'] = ($debug['selectionMethod'] ?? '') . ' → fallback_filesystem';
            return [
                'content' => $this->getFilesystemTemplate(),
                'debug' => $debug,
            ];
        }

        $debug['selectedTemplateName'] = $template->getName();
        $debug['selectedTemplateValidFrom'] = $template->getValidFrom()?->format('Y-m-d');

        return [
            'content' => $template->getContent(),
            'debug' => $debug,
        ];
    }

    /**
     * Versucht ein Datum aus verschiedenen Formaten zu parsen.
     *
     * Der '!'-Prefix in den Formaten setzt alle nicht angegebenen Felder
     * (insb. die Uhrzeit) auf 0, damit die DATE-Vergleiche in der DB
     * nicht durch die aktuelle Uhrzeit verfälscht werden.
     */
    private function parseDate(string $dateString): ?\DateTimeInterface
    {
        $input = trim($dateString);
        if ($input === '') {
            return null;
        }

        // Formate mit '!' Prefix: setzt Uhrzeit auf 00:00:00
        // Reihenfolge: erst vierstellige Jahre, dann zweistellige
        $formats = [
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

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $input);
            if ($date === false) {
                continue;
            }

            // Prüfe auf Parse-Warnungen (z.B. trailing data, overflow)
            $errors = \DateTime::getLastErrors();
            if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                continue;
            }

            // Jahreszahl-Plausibilität: PHP akzeptiert z.B. "26" als vierstelliges Jahr 0026
            // bei Format Y. Nur Jahre zwischen 1970 und 2099 akzeptieren.
            $year = (int) $date->format('Y');
            if ($year < 1970 || $year > 2099) {
                continue;
            }

            return $date;
        }

        // Letzter Versuch via strtotime
        $ts = strtotime($input);
        if ($ts !== false) {
            $date = new \DateTime();
            $date->setTimestamp($ts);
            $date->setTime(0, 0, 0);
            return $date;
        }

        return null;
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
