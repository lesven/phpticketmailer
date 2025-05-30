<?php
/**
 * EmailLogController.php
 * 
 * Controller für das Versandprotokoll (Userstory 20).
 * Zeigt die letzten 100 versendeten E-Mails und ermöglicht die Suche nach Ticket-ID.
 */

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\EmailSentRepository;

class EmailLogController extends AbstractController
{
    private $emailSentRepository;

    public function __construct(EmailSentRepository $emailSentRepository)
    {
        $this->emailSentRepository = $emailSentRepository;
    }

    #[Route('/versandprotokoll', name: 'email_log')]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search');
        if ($search) {
            $emails = $this->emailSentRepository->createQueryBuilder('e')
                ->where('e.ticketId LIKE :search')
                ->setParameter('search', $search.'%')
                ->orderBy('e.timestamp', 'DESC')
                ->getQuery()
                ->getResult();
        } else {
            $emails = $this->emailSentRepository->findBy([], ['timestamp' => 'DESC'], 100);
        }
        return $this->render('email_log/index.html.twig', [
            'emails' => $emails,
            'search' => $search,
        ]);
    }
}
