<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class AdminSecurityController extends AbstractController
{
    #[Route("/login", name: "app_admin_login", methods: ["GET", "POST"])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Already authenticated operators go straight to the command centre
        // rather than seeing the form again.
        if ($this->getUser() !== null) {
            return $this->redirectToRoute("app_admin_telemetry");
        }

        return $this->render("admin/login.html.twig", [
            "last_username" => $authenticationUtils->getLastUsername(),
            "error" => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route("/logout", name: "app_admin_logout", methods: ["GET"])]
    public function logout(): never
    {
        // Intercepted by the firewall's logout listener; never reached.
        throw new \LogicException("This method should never be called.");
    }
}
