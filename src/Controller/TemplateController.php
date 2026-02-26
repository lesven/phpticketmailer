<?php

namespace App\Controller;

use App\Entity\EmailTemplate;
use App\Service\TemplateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/template')]
class TemplateController extends AbstractController
{
    /**
     * @param TemplateService $templateService Service für Template-CRUD und -Auswahl
     * @param string $projectDir Absoluter Pfad zum Projektverzeichnis
     */
    public function __construct(
        private readonly TemplateService $templateService,
        private readonly string $projectDir
    ) {
    }

    /**
     * Hauptseite: Template-Verwaltung mit Sidebar.
     * Wenn eine templateId übergeben wird, wird dieses Template zur Bearbeitung geladen.
     */
    #[Route('/', name: 'template_manage')]
    public function manage(Request $request): Response
    {
        $templates = $this->templateService->getAllTemplates();
        $selectedId = $request->query->getInt('templateId', 0);

        // Ausgewähltes Template laden (oder null wenn keines gewählt)
        $selectedTemplate = null;
        if ($selectedId > 0) {
            $selectedTemplate = $this->templateService->getTemplate($selectedId);
        }

        // Vorschaudaten
        $previewData = $this->getPreviewData();

        // Template-Inhalt und Vorschau
        $templateContent = '';
        $previewContent = '';
        if ($selectedTemplate !== null) {
            $templateContent = $selectedTemplate->getContent();
            $previewContent = $this->replacePlaceholders($templateContent, $previewData);
        }

        return $this->render('template/manage.html.twig', [
            'templates' => $templates,
            'selectedTemplate' => $selectedTemplate,
            'templateContent' => $templateContent,
            'previewContent' => $previewContent,
            'previewData' => $previewData,
        ]);
    }

    /**
     * Neues Template erstellen.
     */
    #[Route('/create', name: 'template_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $name = trim($request->request->get('template_name', ''));
        $validFromStr = $request->request->get('template_valid_from', '');

        if ($name === '') {
            $this->addFlash('error', 'Bitte geben Sie einen Template-Namen ein.');
            return $this->redirectToRoute('template_manage');
        }

        if ($validFromStr === '') {
            $this->addFlash('error', 'Bitte wählen Sie ein Gültig-Ab-Datum.');
            return $this->redirectToRoute('template_manage');
        }

        $validFrom = new \DateTime($validFromStr);
        $template = $this->templateService->createTemplate($name, $validFrom);

        $this->addFlash('success', sprintf('Template "%s" wurde erstellt.', $name));

        return $this->redirectToRoute('template_manage', ['templateId' => $template->getId()]);
    }

    /**
     * Template-Metadaten (Name, Gültig-Ab) aktualisieren.
     */
    #[Route('/{id}/update', name: 'template_update', methods: ['POST'])]
    public function update(int $id, Request $request): Response
    {
        $template = $this->templateService->getTemplate($id);
        if ($template === null) {
            $this->addFlash('error', 'Template nicht gefunden.');
            return $this->redirectToRoute('template_manage');
        }

        $name = trim($request->request->get('template_name', ''));
        $validFromStr = $request->request->get('template_valid_from', '');

        if ($name !== '') {
            $template->setName($name);
        }

        if ($validFromStr !== '') {
            $template->setValidFrom(new \DateTime($validFromStr));
        }

        $this->templateService->saveTemplate($template);
        $this->addFlash('success', sprintf('Template "%s" wurde aktualisiert.', $template->getName()));

        return $this->redirectToRoute('template_manage', ['templateId' => $id]);
    }

    /**
     * Template löschen.
     */
    #[Route('/{id}/delete', name: 'template_delete', methods: ['POST'])]
    public function delete(int $id): Response
    {
        $template = $this->templateService->getTemplate($id);
        if ($template === null) {
            $this->addFlash('error', 'Template nicht gefunden.');
            return $this->redirectToRoute('template_manage');
        }

        $name = $template->getName();
        $this->templateService->deleteTemplate($template);
        $this->addFlash('success', sprintf('Template "%s" wurde gelöscht.', $name));

        return $this->redirectToRoute('template_manage');
    }

    /**
     * WYSIWYG-Inhalt für das ausgewählte Template speichern.
     */
    #[Route('/{id}/save-wysiwyg', name: 'template_save_wysiwyg', methods: ['POST'])]
    public function saveWysiwyg(int $id, Request $request): Response
    {
        $template = $this->templateService->getTemplate($id);
        if ($template === null) {
            $this->addFlash('error', 'Template nicht gefunden.');
            return $this->redirectToRoute('template_manage');
        }

        $content = $request->request->get('template_content', '');
        if (empty($content)) {
            $this->addFlash('error', 'Der Template-Inhalt darf nicht leer sein.');
            return $this->redirectToRoute('template_manage', ['templateId' => $id]);
        }

        $template->setContent($content);
        $this->templateService->saveTemplate($template);
        $this->addFlash('success', 'Das Template wurde erfolgreich gespeichert.');

        return $this->redirectToRoute('template_manage', ['templateId' => $id]);
    }

    /**
     * Datei hochladen und Inhalt dem ausgewählten Template zuordnen.
     */
    #[Route('/{id}/upload', name: 'template_upload', methods: ['POST'])]
    public function upload(int $id, Request $request): Response
    {
        $template = $this->templateService->getTemplate($id);
        if ($template === null) {
            $this->addFlash('error', 'Template nicht gefunden.');
            return $this->redirectToRoute('template_manage');
        }

        $file = $request->files->get('template_file');
        if (!$file instanceof UploadedFile) {
            $this->addFlash('error', 'Bitte wählen Sie eine Datei aus.');
            return $this->redirectToRoute('template_manage', ['templateId' => $id]);
        }

        $content = file_get_contents($file->getPathname());
        if ($content === false || trim($content) === '') {
            $this->addFlash('error', 'Die hochgeladene Datei ist leer.');
            return $this->redirectToRoute('template_manage', ['templateId' => $id]);
        }

        $template->setContent($content);
        $this->templateService->saveTemplate($template);
        $this->addFlash('success', 'Template-Inhalt wurde aus Datei übernommen.');

        return $this->redirectToRoute('template_manage', ['templateId' => $id]);
    }

    /**
     * Template-Inhalt als Datei herunterladen.
     */
    #[Route('/{id}/download', name: 'template_download')]
    public function download(int $id): Response
    {
        $template = $this->templateService->getTemplate($id);
        if ($template === null) {
            $this->addFlash('error', 'Template nicht gefunden.');
            return $this->redirectToRoute('template_manage');
        }

        $content = $template->getContent();
        $filename = $this->slugifyName($template->getName()) . '.html';

        $response = new Response($content);
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename
        );
        $response->headers->set('Content-Disposition', $disposition);
        $response->headers->set('Content-Type', 'text/html');

        return $response;
    }

    // ── Hilfsmethoden ──────────────────────────────────────────

    /**
     * Erzeugt Beispieldaten für die Template-Vorschau im Editor.
     *
     * Delegiert an TemplateService::getPreviewData().
     *
     * @return array<string, string> Platzhalter-Schlüssel => Beispielwerte
     */
    private function getPreviewData(): array
    {
        return $this->templateService->getPreviewData();
    }

    /**
     * Ersetzt Template-Platzhalter (z.B. {{ticketId}}) durch die übergebenen Daten.
     *
     * Delegiert an TemplateService::replacePlaceholders().
     *
     * @param string $template  Der Template-Inhalt mit Platzhaltern
     * @param array  $data      Assoziatives Array mit Ersetzungswerten
     * @return string Der Template-Inhalt mit eingesetzten Werten
     */
    private function replacePlaceholders(string $template, array $data): string
    {
        return $this->templateService->replacePlaceholders($template, $data);
    }

    /**
     * Wandelt einen Template-Namen in einen dateisystemfreundlichen Slug um.
     *
     * @param string $name Der Template-Name
     * @return string Der Slug (Kleinbuchstaben, Sonderzeichen als Unterstriche)
     */
    private function slugifyName(string $name): string
    {
        $slug = mb_strtolower($name);
        $slug = preg_replace('/[^a-z0-9_-]+/', '_', $slug);
        $slug = trim($slug, '_');

        return $slug ?: 'template';
    }
}