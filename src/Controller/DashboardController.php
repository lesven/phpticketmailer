<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\EmailSentRepository;

class DashboardController extends AbstractController
{
    private $emailSentRepository;
    
    public function __construct(EmailSentRepository $emailSentRepository)
    {
        $this->emailSentRepository = $emailSentRepository;
    }
    
    /**
     * @Route("/", name="dashboard")
     */
    public function index(): Response
    {
        // Hole die letzten Versandaktionen
        $recentEmails = $this->emailSentRepository->findBy(
            [], 
            ['timestamp' => 'DESC'], 
            10
        );
        
        return $this->render('dashboard/index.html.twig', [
            'recentEmails' => $recentEmails,
        ]);
    }
}