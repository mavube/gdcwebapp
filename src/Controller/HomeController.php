<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\TrainingProgramRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route("/", name: "app_home", methods: ["GET"])]
    public function index(TrainingProgramRepository $repository): Response
    {
        return $this->render("home/index.html.twig", [
            "programs" => $repository->findBy([], ["title" => "ASC"]),
        ]);
    }
}
