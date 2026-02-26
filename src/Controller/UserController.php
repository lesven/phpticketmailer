<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Form\UserImportType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\UserImportService;
use App\Dto\UserListingCriteria;
use App\Service\UserListingService;

#[Route('/user')]
class UserController extends AbstractController
{
    /**
     * Zeigt die Benutzerliste mit Paginierung, Suche und Sortierung.
     * Der Controller baut nur das Request-Objekt auf und überlässt die
     * Filter-/Sortierlogik dem `UserListingService`.
     */
    #[Route('/', name: 'user_index', methods: ['GET'])]
    public function index(Request $request, UserListingService $listingService): Response
    {
        $criteria = UserListingCriteria::fromRequest($request);
        $listingResult = $listingService->listUsers($criteria);

        return $this->render('user/index.html.twig', [
            'users' => $listingResult->users,
            'searchTerm' => $listingResult->searchTerm,
            'sortField' => $listingResult->sortField,
            'sortDirection' => $listingResult->sortDirection,
            'oppositeDirection' => $listingResult->getOppositeDirection(),
            'pagination' => $listingResult->pagination,
            'hasSearch' => $listingResult->hasSearch(),
            // Template compatibility variables
            'currentPage' => $listingResult->pagination ? $listingResult->pagination->currentPage : 1,
            'totalPages' => $listingResult->pagination ? $listingResult->pagination->totalPages : 1,
            'totalUsers' => $listingResult->pagination ? $listingResult->pagination->totalItems : count($listingResult->users),
        ]);
    }

    /**
     * Exportiert alle Benutzer als CSV-Datei und liefert eine Download-Response.
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
     * Importiert Benutzer aus einer CSV-Datei über das Upload-Formular.
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
     * Erstellt einen neuen Benutzer mithilfe des `UserType`-Formulars.
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
     * Bearbeitet einen bestehenden Benutzer und speichert Änderungen.
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
     * Löscht einen Benutzer nach CSRF-Prüfung und zeigt einen Flash.
     */
    #[Route('/{id}', name: 'user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();

            $this->addFlash('success', 'Benutzer erfolgreich gelöscht!');
        }
        
        return $this->redirectToRoute('user_index');
    }

    /**
     * Schaltet den Umfrage-Ausschluss für den Nutzer um und leitet zurück zur Liste.
     */
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