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
            'ticketName' => 'Beispiel Support-Anfrage',
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
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = 'email_template.txt';
                
                try {
                    $file->move(
                        $this->getTemplateDirectory(),
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
        ]);
    }
    
    #[Route('/download', name: 'template_download')]
    public function download(): Response
    {
        $templatePath = $this->getTemplatePath();
        
        if (!file_exists($templatePath)) {
            // Falls kein Template existiert, erstellen wir ein Standard-Template
            $defaultTemplate = $this->getDefaultTemplate();
            file_put_contents($templatePath, $defaultTemplate);
        }
        
        $response = new BinaryFileResponse($templatePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'email_template.txt'
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
            '{{ticketName}}' => $data['ticketName'] ?? 'Ticket-Name',
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
Sehr geehrte(r) {{username}},

wir möchten gerne Ihre Meinung zu dem kürzlich bearbeiteten Ticket erfahren:

Ticket-Nr: {{ticketId}}
Betreff: {{ticketName}}

Um das Ticket anzusehen und Feedback zu geben, klicken Sie bitte hier: {{ticketLink}}

Vielen Dank für Ihre Rückmeldung!

Mit freundlichen Grüßen
Ihr Support-Team
EOT;
    }
    
    private function getTemplateDirectory(): string
    {
        $dir = $this->projectDir . '/templates/emails';
        
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        return $dir;
    }
    
    private function getTemplatePath(): string
    {
        return $this->getTemplateDirectory() . '/email_template.txt';
    }
}