<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * @Route("/template")
 */
class TemplateController extends AbstractController
{
    private $projectDir;
    private $slugger;
    
    public function __construct(string $projectDir, SluggerInterface $slugger)
    {
        $this->projectDir = $projectDir;
        $this->slugger = $slugger;
    }
    
    /**
     * @Route("/", name="template_manage")
     */
    public function manage(Request $request): Response
    {
        $templatePath = $this->getTemplatePath();
        $templateExists = file_exists($templatePath);
        $message = null;
        
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
        
        return $this->render('template/manage.html.twig', [
            'templateExists' => $templateExists,
            'message' => $message,
        ]);
    }
    
    /**
     * @Route("/download", name="template_download")
     */
    public function download(): Response
    {
        $templatePath = $this->getTemplatePath();
        
        if (!file_exists($templatePath)) {
            throw $this->createNotFoundException('E-Mail-Template nicht gefunden.');
        }
        
        $response = new BinaryFileResponse($templatePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'email_template.txt'
        );
        
        return $response;
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