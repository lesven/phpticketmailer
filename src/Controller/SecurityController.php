<?php

namespace App\Controller;

use App\Exception\WeakPasswordException;
use App\Service\AuthenticationService;
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
    public function __construct(
        private readonly AuthenticationService $authenticationService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/login', name: 'app_login')]
    public function login(Request $request, SessionInterface $session): Response
    {
        if ($session->get('is_authenticated')) {
            return $this->redirectToRoute('dashboard');
        }

        $error = null;
        
        if ($request->isMethod('POST')) {
            try {
                $token = new CsrfToken('authenticate', $request->request->get('_csrf_token'));
                if (!$this->csrfTokenManager->isTokenValid($token)) {
                    throw new InvalidCsrfTokenException('Invalid CSRF token');
                }
                
                $password = $request->request->get('password');

                if ($this->authenticationService->authenticate($password)) {
                    $session->set('is_authenticated', true);
                    return $this->redirectToRoute('dashboard');
                }

                $error = 'Ungültiges Passwort';
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
                
                if ($this->authenticationService->changePassword($currentPassword, $newPassword)) {
                    $success = 'Passwort erfolgreich geändert.';
                } else {
                    $error = 'Das aktuelle Passwort ist nicht korrekt.';
                }
            } catch (WeakPasswordException $e) {
                $error = 'Schwaches Passwort: ' . $e->getMessage();
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