<?php
/**
 * EmailLogController.php
 * 
 * Controller für das Versandprotokoll (Userstory 20).
 * Zeigt versendete E-Mails mit Paginierung und ermöglicht die Suche nach Ticket-ID.
 */

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\EmailSentRepository;
use App\Service\PaginationService;

class EmailLogController extends AbstractController
{
    private $emailSentRepository;

    public function __construct(EmailSentRepository $emailSentRepository)
    {
        $this->emailSentRepository = $emailSentRepository;
    }

    #[Route('/versandprotokoll', name: 'email_log')]
    public function index(Request $request, PaginationService $paginationService): Response
    {
        $search = $request->query->get('search');
        $filter = $request->query->get('filter', 'all');
        $page = max(1, (int) $request->query->get('page', 1));

        $queryBuilder = $this->emailSentRepository->createFilteredQueryBuilder($filter);

        if ($search) {
            $queryBuilder
                ->andWhere('e.ticketId LIKE :search')
                ->setParameter('search', $search . '%');

            $emails = $queryBuilder->getQuery()->getResult();
            $pagination = null;
        } else {
            $pagination = $paginationService->paginate($queryBuilder, $page, 50);
            $emails = $pagination->results;
        }

        return $this->render('email_log/index.html.twig', [
            'emails' => $emails,
            'search' => $search,
            'filter' => $filter,
            'pagination' => $pagination,
            'currentPage' => $pagination ? $pagination->currentPage : 1,
            'totalPages' => $pagination ? $pagination->totalPages : 1,
        ]);
    }
}
