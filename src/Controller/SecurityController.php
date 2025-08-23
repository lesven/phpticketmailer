<?php

namespace App\Controller;

use App\Entity\AdminPassword;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class SecurityController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private CsrfTokenManagerInterface $csrfTokenManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokenManager
    ) {
        $this->entityManager = $entityManager;
        $this->csrfTokenManager = $csrfTokenManager;
    }

    #[Route('/login', name: 'app_login')]
    public function login(Request $request, SessionInterface $session): Response
    {
        // Wenn der Benutzer bereits authentifiziert ist, leite zum Dashboard um
        if ($session->get('is_authenticated')) {
            return $this->redirectToRoute('dashboard');
        }

        $error = null;
        
        // Überprüfe, ob ein Formular übermittelt wurde
        if ($request->isMethod('POST')) {
            try {
                // CSRF-Token validieren
                $token = new CsrfToken('authenticate', $request->request->get('_csrf_token'));
                if (!$this->csrfTokenManager->isTokenValid($token)) {
                    throw new InvalidCsrfTokenException('Invalid CSRF token');
                }
                
                $password = $request->request->get('password');

                // Manuell eine neue AdminPassword-Entity erstellen und in die DB speichern
                try {
                    // Versuchen wir zu finden, ob es bereits einen Eintrag gibt
                    $adminPasswordRepo = $this->entityManager->getRepository(AdminPassword::class);
                    $adminPassword = $adminPasswordRepo->findOneBy([], ['id' => 'ASC']);
                    
                    // Wenn kein Passwort gesetzt ist (erster Start), setze das Standardpasswort
                    if (!$adminPassword) {
                        $adminPassword = new AdminPassword();
                        $adminPassword->setPasswordFromPlaintext('DefaultP@ssw0rd123!');
                        $this->entityManager->persist($adminPassword);
                        $this->entityManager->flush();
                    }
                    
                    // Prüfe das Passwort
                    if ($adminPassword->verifyPassword($password)) {
                        $session->set('is_authenticated', true);
                        return $this->redirectToRoute('dashboard');
                    } else {
                        $error = 'Ungültiges Passwort';
                    }
                } catch (\Exception $e) {
                    $error = 'Fehler bei der Authentifizierung: ' . $e->getMessage();
                }
            } catch (\Exception $e) {
                $error = 'Ein Fehler ist aufgetreten: ' . $e->getMessage();
            }
        }
        
        return $this->render('security/login.html.twig', [
            'error' => $error,
        ]);
    }
    
    #[Route('/logout', name: 'app_logout')]
    public function logout(SessionInterface $session): Response
    {
        $session->remove('is_authenticated');
        return $this->redirectToRoute('app_login');
    }
    
    #[Route('/password', name: 'change_password')]
    public function changePassword(Request $request): Response
    {
        $error = null;
        $success = null;
        
        if ($request->isMethod('POST')) {
            try {
                $token = new CsrfToken('change_password', $request->request->get('_csrf_token'));
                if (!$this->csrfTokenManager->isTokenValid($token)) {
                    throw new InvalidCsrfTokenException('Invalid CSRF token');
                }
                
                $currentPassword = $request->request->get('current_password');
                $newPassword = $request->request->get('new_password');
                
                $adminPassword = $this->entityManager->getRepository(AdminPassword::class)->findOneBy([], ['id' => 'ASC']);
                
                if ($adminPassword && $adminPassword->verifyPassword($currentPassword)) {
                    try {
                        // Setze neues Passwort (SecurePassword validiert automatisch die Stärke)
                        $adminPassword->setPasswordFromPlaintext($newPassword);
                        $this->entityManager->flush();
                        
                        $success = 'Passwort erfolgreich geändert.';
                    } catch (\App\Exception\WeakPasswordException $e) {
                        $error = 'Schwaches Passwort: ' . $e->getMessage();
                    }
                } else {
                    $error = 'Das aktuelle Passwort ist nicht korrekt.';
                }
            } catch (\Exception $e) {
                $error = 'Ein Fehler ist aufgetreten: ' . $e->getMessage();
            }
        }
        
        return $this->render('security/change_password.html.twig', [
            'error' => $error,
            'success' => $success,
        ]);
    }
}