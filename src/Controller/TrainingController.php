<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CourseInquiry;
use App\Form\CourseInquiryType;
use App\Repository\ContentBlockRepository;
use App\Repository\ContentItemRepository;
use App\Repository\TrainingProgramRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class TrainingController extends AbstractController
{
    #[Route("/training/{slug}", name: "app_training_show", methods: ["GET"], requirements: ["slug" => "[a-z0-9-]+"])]
    public function show(
        string $slug,
        TrainingProgramRepository $repository,
        ContentBlockRepository $blocks,
        ContentItemRepository $items,
    ): Response {
        $program = $repository->findOneBy(["slug" => $slug]);

        if (!$program) {
            throw $this->createNotFoundException("The requested corporate track does not exist.");
        }

        $inquiry = new CourseInquiry();
        $inquiry->setTrainingProgram($program);
        $form = $this->createForm(CourseInquiryType::class, $inquiry, [
            "action" => $this->generateUrl("app_inquiry_submit", ["slug" => $slug]),
        ]);

        return $this->render("training/show.html.twig", [
            "program" => $program,
            "inquiryForm" => $form,
            // The solution cards and their heading are editable content, so the
            // page renders them from the same sources the home page uses.
            "blocks" => $blocks->findActiveKeyed(),
            "items" => $items->findActiveGroupedBySection(),
        ]);
    }

    #[Route("/training/{slug}/inquiry", name: "app_inquiry_submit", methods: ["POST"], requirements: ["slug" => "[a-z0-9-]+"])]
    public function submit(
        string $slug,
        Request $request,
        TrainingProgramRepository $repository,
        ContentBlockRepository $blocks,
        ContentItemRepository $items,
        EntityManagerInterface $em,
        RateLimiterFactory $inquiryLimiter,
    ): Response {
        $program = $repository->findOneBy(["slug" => $slug]);
        if (!$program) {
            throw $this->createNotFoundException("The requested corporate track does not exist.");
        }

        $limiter = $inquiryLimiter->create($request->getClientIp() ?? "unknown");
        if (!$limiter->consume()->isAccepted()) {
            $this->addFlash("error", "Too many requests. Please try again in a few minutes.");

            return $this->redirectToRoute("app_training_show", ["slug" => $slug]);
        }

        $inquiry = new CourseInquiry();
        $inquiry->setTrainingProgram($program);
        $form = $this->createForm(CourseInquiryType::class, $inquiry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($inquiry);
            $em->flush();

            $this->addFlash("success", "Thank you. A consultant will contact you within one business day.");

            return $this->redirectToRoute("app_training_show", ["slug" => $slug]);
        }

        return $this->render("training/show.html.twig", [
            "program" => $program,
            "inquiryForm" => $form,
            "blocks" => $blocks->findActiveKeyed(),
            "items" => $items->findActiveGroupedBySection(),
        ], new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
