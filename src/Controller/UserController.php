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
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/user")
 */
class UserController extends AbstractController
{
    /**
     * @Route("/", name="user_index", methods={"GET"})
     */
    public function index(Request $request, UserRepository $userRepository): Response
    {
        // Get search term from request query parameters
        $searchTerm = $request->query->get('search');
        
        // Get sort parameters from request
        $sortField = $request->query->get('sort', 'id');
        $sortDirection = $request->query->get('direction', 'ASC');
        
        // Use searchByUsername method from repository with sorting parameters
        $users = $userRepository->searchByUsername($searchTerm, $sortField, $sortDirection);
        
        // Determine the opposite direction for toggling sort order
        $oppositeDirection = $sortDirection === 'ASC' ? 'DESC' : 'ASC';
        
        return $this->render('user/index.html.twig', [
            'users' => $users,
            'searchTerm' => $searchTerm,
            'sortField' => $sortField,
            'sortDirection' => $sortDirection,
            'oppositeDirection' => $oppositeDirection
        ]);
    }

    /**
     * @Route("/export", name="user_export", methods={"GET"})
     */
    public function export(UserRepository $userRepository): Response
    {
        $users = $userRepository->findAll();
        
        // Create CSV content
        $csvContent = "ID,Username,Email\n";
        
        foreach ($users as $user) {
            $csvContent .= $user->getId() . ',' . 
                          '"' . str_replace('"', '""', $user->getUsername()) . '"' . ',' . 
                          '"' . str_replace('"', '""', $user->getEmail()) . '"' . "\n";
        }
        
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
     * @Route("/import", name="user_import", methods={"GET","POST"})
     */
    public function import(Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(UserImportType::class);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $csvFile = $form->get('csvFile')->getData();
            $clearExisting = $form->get('clearExisting')->getData();
            
            // Bestehende Benutzer bei Bedarf löschen
            if ($clearExisting) {
                $this->clearExistingUsers($entityManager);
            }
            
            // CSV-Datei verarbeiten
            if ($csvFile) {
                $importResult = $this->processCsvFile($csvFile, $entityManager);
                if ($importResult === false) {
                    return $this->redirectToRoute('user_import');
                }
                
                list($imported, $skipped) = $importResult;
                $this->addFlash('success', "$imported Benutzer erfolgreich importiert. $skipped Einträge übersprungen.");
                return $this->redirectToRoute('user_index');
            }
        }
        
        return $this->render('user/import.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    
    /**
     * Löscht alle bestehenden Benutzer aus der Datenbank
     */
    private function clearExistingUsers(EntityManagerInterface $entityManager): void
    {
        $users = $entityManager->getRepository(User::class)->findAll();
        foreach ($users as $user) {
            $entityManager->remove($user);
        }
        $entityManager->flush();
        $this->addFlash('info', 'Alle bestehenden Benutzer wurden gelöscht.');
    }
    
    /**
     * Verarbeitet die hochgeladene CSV-Datei
     * 
     * @return array|false [Anzahl importierter, Anzahl übersprungener] oder false bei Fehler
     */
    private function processCsvFile($csvFile, EntityManagerInterface $entityManager)
    {
        $handle = fopen($csvFile->getPathname(), 'r');
        
        // Erste Zeile für Header lesen
        $header = fgetcsv($handle, 1000, ',');
        
        if ($header === false) {
            $this->addFlash('error', 'Die CSV-Datei ist leer oder fehlerhaft.');
            fclose($handle);
            return false;
        }
        
        // Indizes für die benötigten Spalten finden
        $emailIndex = array_search('email', array_map('strtolower', $header));
        $usernameIndex = array_search('benutzername', array_map('strtolower', $header));
        
        // Wenn die erforderlichen Spalten nicht gefunden wurden
        if ($emailIndex === false || $usernameIndex === false) {
            $this->addFlash('error', 'Die CSV-Datei hat nicht die erforderlichen Spalten "email" und "benutzername".');
            fclose($handle);
            return false;
        }
        
        $importStats = $this->importUsersFromCsv($handle, $emailIndex, $usernameIndex, $entityManager);
        fclose($handle);
        
        return $importStats;
    }
    
    /**
     * Importiert Benutzer aus dem geöffneten CSV-Handle
     * 
     * @return array [Anzahl importierter, Anzahl übersprungener]
     */
    private function importUsersFromCsv($handle, $emailIndex, $usernameIndex, EntityManagerInterface $entityManager): array
    {
        $imported = 0;
        $skipped = 0;
        $existingUsernames = [];
        
        // Sammle alle existierenden Benutzernamen
        $existingUsers = $entityManager->getRepository(User::class)->findAll();
        foreach ($existingUsers as $user) {
            $existingUsernames[$user->getUsername()] = $user;
        }
        
        // CSV-Zeilen verarbeiten
        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            if (count($row) <= max($emailIndex, $usernameIndex)) {
                $skipped++;
                continue;
            }
            
            $username = trim($row[$usernameIndex]);
            $email = trim($row[$emailIndex]);
            
            // Prüfe, ob Benutzername und E-Mail vorhanden sind
            if (empty($username) || empty($email)) {
                $skipped++;
                continue;
            }
            
            // Prüfe, ob ein Benutzer mit diesem Benutzernamen bereits existiert
            if (isset($existingUsernames[$username])) {
                // Aktualisiere die E-Mail-Adresse des bestehenden Benutzers
                $user = $existingUsernames[$username];
                $user->setEmail($email);
            } else {
                // Erstelle neuen Benutzer
                $user = new User();
                $user->setUsername($username);
                $user->setEmail($email);
                $entityManager->persist($user);
                $existingUsernames[$username] = $user; // Füge zur Liste der bekannten Benutzer hinzu
            }
            
            $imported++;
        }
        
        $entityManager->flush();
        return [$imported, $skipped];
    }

    /**
     * @Route("/new", name="user_new", methods={"GET","POST"})
     */
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
     * @Route("/{id}/edit", name="user_edit", methods={"GET","POST"})
     */
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
     * @Route("/{id}", name="user_delete", methods={"POST"})
     */
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();
            
            $this->addFlash('success', 'Benutzer erfolgreich gelöscht!');
        }

        return $this->redirectToRoute('user_index');
    }
}