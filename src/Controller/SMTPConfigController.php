<?php

namespace App\Controller;

use App\Entity\SMTPConfig;
use App\Form\SMTPConfigType;
use App\Repository\SMTPConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport;

/**
 * @Route("/smtp-config")
 */
class SMTPConfigController extends AbstractController
{
    private $entityManager;
    private $smtpConfigRepository;
    
    public function __construct(EntityManagerInterface $entityManager, SMTPConfigRepository $smtpConfigRepository)
    {
        $this->entityManager = $entityManager;
        $this->smtpConfigRepository = $smtpConfigRepository;
    }
    
    /**
     * @Route("/", name="smtp_config_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, MailerInterface $mailer): Response
    {
        $config = $this->smtpConfigRepository->getConfig();
        
        // Falls keine Konfiguration existiert, erstelle eine neue
        if (!$config) {
            $config = new SMTPConfig();
            // Standardwerte setzen
            $config->setHost('localhost');
            $config->setPort(25);
            $config->setSenderEmail('noreply@example.com');
            $config->setSenderName('Ticket-System');
            $config->setUseTLS(false);
        }
        
        $form = $this->createForm(SMTPConfigType::class, $config);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            // Testmail-Adresse aus der Anfrage holen
            $testEmail = $request->request->get('test_email');
            
            // Konfiguration speichern
            $this->entityManager->persist($config);
            $this->entityManager->flush();
            
            // Wenn eine Test-Email angegeben wurde, senden wir eine Testmail
            if (!empty($testEmail)) {
                try {
                    // Transport mit neuer Konfiguration erstellen
                    $transport = Transport::fromDsn($config->getDSN());
                    
                    // E-Mail erstellen
                    $email = (new Email())
                        ->from($config->getSenderEmail())
                        ->to($testEmail)
                        ->subject('SMTP Konfigurationstest')
                        ->text('Dies ist eine Testnachricht zur Überprüfung der SMTP-Konfiguration.')
                        ->html('<p>Dies ist eine Testnachricht zur Überprüfung der SMTP-Konfiguration.</p>');
                    
                    // E-Mail senden
                    $transport->send($email);
                    
                    $this->addFlash('success', 'Die SMTP-Konfiguration wurde gespeichert und die Test-E-Mail erfolgreich an ' . $testEmail . ' gesendet.');
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Die Konfiguration wurde gespeichert, aber die Test-E-Mail konnte nicht gesendet werden: ' . $e->getMessage());
                }
            } else {
                $this->addFlash('success', 'SMTP-Konfiguration wurde erfolgreich gespeichert.');
            }
            
            return $this->redirectToRoute('smtp_config_edit');
        }
        
        return $this->render('smtp_config/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}