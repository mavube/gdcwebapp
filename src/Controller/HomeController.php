<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\Pillar;
use App\Repository\ContentBlockRepository;
use App\Repository\ContentItemRepository;
use App\Repository\ServiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route("/", name: "app_home", methods: ["GET"])]
    public function index(
        ServiceRepository $services,
        ContentBlockRepository $blocks,
        ContentItemRepository $items,
    ): Response {
        $grouped = $services->findPublishedGroupedByPillar();

        return $this->render("home/index.html.twig", [
            "pillars" => Pillar::cases(),
            "grouped" => $grouped,
            "totalServices" => $services->countPublished(),
            // Keyed by block key so a template asks for the copy it needs.
            "blocks" => $blocks->findActiveKeyed(),
            // Keyed by section so each list renders from one query.
            "items" => $items->findActiveGroupedBySection(),
        ]);
    }
}
