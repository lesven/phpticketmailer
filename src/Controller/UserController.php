<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Form\UserImportType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\PaginationService;
use App\Service\UserImportService;

#[Route('/user')]
class UserController extends AbstractController
{
    /**
     * Zeigt die Benutzerliste mit Paginierung, Suche und Sortierung
     */
    #[Route('/', name: 'user_index', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository, PaginationService $paginationService): Response
    {
        // Parameter aus Request extrahieren
        $searchTerm = $request->query->get('search');
        $sortField = $request->query->get('sort', 'id');
        $sortDirection = $request->query->get('direction', 'ASC');
        $page = max(1, (int) $request->query->get('page', 1));
        
        // Bei Suchanfrage: alle Ergebnisse ohne Paginierung anzeigen
        if ($searchTerm) {
            $users = $userRepository->searchByUsername($searchTerm, $sortField, $sortDirection);
            $paginationResult = null;
        } else {
            // Paginierung verwenden wenn keine Suche aktiv
            $queryBuilder = $userRepository->createSortedQueryBuilder($sortField, $sortDirection);
            $paginationResult = $paginationService->paginate($queryBuilder, $page);
            $users = $paginationResult->results;
        }
          return $this->render('user/index.html.twig', [
            'users' => $users,
            'searchTerm' => $searchTerm,
            'sortField' => $sortField,
            'sortDirection' => $sortDirection,
            'oppositeDirection' => $sortDirection === 'ASC' ? 'DESC' : 'ASC',
            'pagination' => $paginationResult,
            'hasSearch' => !empty($searchTerm),
            // Template compatibility variables
            'currentPage' => $paginationResult ? $paginationResult->currentPage : 1,
            'totalPages' => $paginationResult ? $paginationResult->totalPages : 1,
            'totalUsers' => $paginationResult ? $paginationResult->totalItems : count($users)
        ]);
    }

    /**
     * Exportiert alle Benutzer als CSV-Datei
     */
    #[Route('/export', name: 'user_export', methods: ['GET'])]
    public function export(UserImportService $userImportService): Response
    {
        $csvContent = $userImportService->exportUsersToCsv();
        
        // Create the response with the CSV content
        $response = new Response($csvContent);
        
        // Set headers for CSV download
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            'users_export_' . date('Y-m-d_H-i-s') . '.csv'
        );
        
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', $disposition);
          return $response;
    }

    /**
     * Importiert Benutzer aus einer CSV-Datei
     */
    #[Route('/import', name: 'user_import', methods: ['GET', 'POST'])]
    public function import(Request $request, UserImportService $userImportService): Response
    {
        $form = $this->createForm(UserImportType::class);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $csvFile = $form->get('csvFile')->getData();
            $clearExisting = $form->get('clearExisting')->getData();
            
            if ($csvFile) {
                $result = $userImportService->importUsersFromCsv($csvFile, $clearExisting);
                
                $this->addFlash($result->getFlashType(), $result->message);
                
                if ($result->success) {
                    return $this->redirectToRoute('user_index');
                }
            }
        }
        
        return $this->render('user/import.html.twig', [
            'form' => $form->createView(),        ]);
    }

    /**
     * Erstellt einen neuen Benutzer
     */
    #[Route('/new', name: 'user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Benutzer erfolgreich erstellt!');
            return $this->redirectToRoute('user_index');
        }

        return $this->render('user/new.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Bearbeitet einen bestehenden Benutzer
     */
    #[Route('/{id}/edit', name: 'user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Benutzer erfolgreich aktualisiert!');
            return $this->redirectToRoute('user_index');
        }

        return $this->render('user/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Löscht einen Benutzer
     */
    #[Route('/{id}', name: 'user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();

            $this->addFlash('success', 'Benutzer erfolgreich gelöscht!');
        }        return $this->redirectToRoute('user_index');
    }

    #[Route('/{id}/toggle-exclude', name: 'user_toggle_exclude', methods: ['POST'])]
    public function toggleExclude(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle_exclude'.$user->getId(), $request->request->get('_token'))) {
            $user->setExcludedFromSurveys(!$user->isExcludedFromSurveys());
            $entityManager->flush();
        }

        $referer = $request->headers->get('referer');
        return $this->redirect($referer ?: $this->generateUrl('user_index'));
    }
}