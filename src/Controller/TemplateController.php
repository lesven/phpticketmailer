<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\ValueObject\TicketName;

#[Route('/template')]
class TemplateController extends AbstractController
{
    private $projectDir;
    private $slugger;
    
    public function __construct(string $projectDir, SluggerInterface $slugger)
    {
        $this->projectDir = $projectDir;
        $this->slugger = $slugger;
    }
    
    #[Route('/', name: 'template_manage')]
    public function manage(Request $request): Response
    {
        $templatePath = $this->getTemplatePath();
        $templateExists = file_exists($templatePath);
        $message = null;
        
        // Beispieldaten für die Vorschau
        $previewData = [
            'ticketId' => 'TICKET-12345',
            'ticketName' => TicketName::fromString('Beispiel Support-Anfrage'),
            'username' => 'max.mustermann',
            'ticketLink' => 'https://www.ticket.de/TICKET-12345'
        ];
        
        // Fälligkeitsdatum für die Vorschau hinzufügen
        $dueDate = new \DateTime();
        $dueDate->modify('+7 days');
        $germanMonths = [
            1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni',
            7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'
        ];
        $previewData['dueDate'] = $dueDate->format('d') . '. ' . $germanMonths[(int)$dueDate->format('n')] . ' ' . $dueDate->format('Y');
          if ($request->isMethod('POST')) {
            $file = $request->files->get('template_file');
            
            if ($file instanceof UploadedFile) {
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $extension = $file->getClientOriginalExtension();
                $newFilename = 'email_template.' . ($extension === 'html' ? 'html' : 'txt');
                $templateDirectory = $this->getTemplateDirectory();
                
                try {
                    // Stellen wir sicher, dass das Verzeichnis existiert
                    if (!is_dir($templateDirectory)) {
                        mkdir($templateDirectory, 0777, true);
                    }
                    
                    $file->move(
                        $templateDirectory,
                        $newFilename
                    );
                    $message = 'Template wurde erfolgreich hochgeladen.';
                    $templateExists = true;
                } catch (\Exception $e) {
                    $message = 'Fehler beim Hochladen des Templates: ' . $e->getMessage();
                }
            }
        }
        
        // Template-Inhalt laden für die Vorschau und standardmäßig erstellen, wenn nicht vorhanden
        $templateContent = '';
        if ($templateExists) {
            $templateContent = file_get_contents($templatePath);
        } else {
            $templateContent = $this->getDefaultTemplate();
            // Standard-Template speichern
            file_put_contents($templatePath, $templateContent);
            $templateExists = true;
        }
        
        // Vorschau mit Beispieldaten generieren
        $previewContent = $this->replacePlaceholders($templateContent, $previewData);
        
        return $this->render('template/manage.html.twig', [
            'templateExists' => $templateExists,
            'message' => $message,
            'previewContent' => $previewContent,
            'previewData' => $previewData,
            'templateContent' => $templateContent,
        ]);
    }
      #[Route('/save-wysiwyg', name: 'template_save_wysiwyg', methods: ['POST'])]
    public function saveWysiwyg(Request $request): Response
    {
        $templateContent = $request->request->get('template_content');
        
        if (!empty($templateContent)) {
            $templateDirectory = $this->getTemplateDirectory();
            
            // Stellen wir sicher, dass das Verzeichnis existiert
            if (!is_dir($templateDirectory)) {
                mkdir($templateDirectory, 0777, true);
            }
            
            // Speichere das Template als HTML-Datei
            file_put_contents($templateDirectory . '/email_template.html', $templateContent);
            
            $this->addFlash('success', 'Das Template wurde erfolgreich gespeichert.');
        } else {
            $this->addFlash('error', 'Der Template-Inhalt darf nicht leer sein.');
        }
        
        return $this->redirectToRoute('template_manage');
    }
      #[Route('/download', name: 'template_download')]
    public function download(): Response
    {
        $templatePath = $this->getTemplatePath();
        $templateDirectory = $this->getTemplateDirectory();
        
        // Stellen wir sicher, dass das Verzeichnis existiert
        if (!is_dir($templateDirectory)) {
            mkdir($templateDirectory, 0777, true);
        }
        
        if (!file_exists($templatePath)) {
            // Falls kein Template existiert, erstellen wir ein Standard-Template
            $defaultTemplate = $this->getDefaultTemplate();
            file_put_contents($templatePath, $defaultTemplate);
        }
        
        $extension = pathinfo($templatePath, PATHINFO_EXTENSION);
        
        $response = new BinaryFileResponse($templatePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'email_template.' . $extension
        );
        
        return $response;
    }
    
    /**
     * Ersetzt Platzhalter im Template mit tatsächlichen Werten
     */
    private function replacePlaceholders(string $template, array $data): string
    {
        // Füge das Fälligkeitsdatum hinzu (aktuelles Datum + 7 Tage) im deutschen Format
        $dueDate = new \DateTime();
        $dueDate->modify('+7 days');
        $germanMonths = [
            1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni',
            7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'
        ];
        $formattedDueDate = $dueDate->format('d') . '. ' . $germanMonths[(int)$dueDate->format('n')] . ' ' . $dueDate->format('Y');
        
        $placeholders = [
            '{{ticketId}}' => $data['ticketId'] ?? 'TICKET-ID',
            '{{ticketName}}' => isset($data['ticketName']) ? (string) $data['ticketName'] : 'Ticket-Name',
            '{{username}}' => $data['username'] ?? 'Benutzername',
            '{{ticketLink}}' => $data['ticketLink'] ?? 'https://www.ticket.de/ticket-id',
            '{{dueDate}}' => $data['dueDate'] ?? $formattedDueDate
        ];
        
        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }
    
    /**
     * Liefert ein Standard-Template zurück
     */
    private function getDefaultTemplate(): string
    {
        return <<<EOT
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
      private function getTemplateDirectory(): string
    {
        $dir = $this->projectDir . '/templates/emails';
        
        // Stellen wir sicher, dass das Verzeichnis existiert
        if (!is_dir($dir)) {
            // Versuche das Verzeichnis zu erstellen mit vollem Pfad
            if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new \RuntimeException(sprintf('Das Verzeichnis "%s" konnte nicht erstellt werden', $dir));
            }
        }
        
        return $dir;
    }
      private function getTemplatePath(): string
    {
        // Prüfe zuerst, ob ein HTML-Template existiert
        $htmlPath = $this->getTemplateDirectory() . '/email_template.html';
        if (file_exists($htmlPath)) {
            return $htmlPath;
        }
        
        // Fallback auf das Text-Template
        $txtPath = $this->getTemplateDirectory() . '/email_template.txt';
        
        // Stellen wir sicher, dass das Verzeichnis existiert
        if (!is_dir(dirname($txtPath))) {
            mkdir(dirname($txtPath), 0777, true);
        }
        
        return $txtPath;
    }
}