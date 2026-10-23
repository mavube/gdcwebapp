<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\CourseInquiryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The internal command centre.
 *
 * Access is enforced twice on purpose:
 *  1. access_control in security.yaml gates the ^/admin/telemetry path.
 *  2. The IsGranted attribute below re-checks the role at the controller.
 *
 * Defence in depth: if a future route rename or firewall reorder ever moved
 * this controller out from under the path rule, the attribute still refuses.
 */
#[Route("/admin/telemetry", name: "app_admin_telemetry", methods: ["GET"])]
#[IsGranted("ROLE_GDC_ADMIN")]
final class AdminTelemetryController extends AbstractController
{
    public function __invoke(CourseInquiryRepository $inquiries): Response
    {
        $leads = $inquiries->findLatest();

        return $this->render("admin/telemetry.html.twig", [
            "leads" => $leads,
            "metrics" => [
                "pending" => $inquiries->countPending(),
                "scheduled" => $inquiries->countScheduledBriefings(),
                "pipelineUsd" => $inquiries->totalPipelineValueUsd(),
            ],
        ]);
    }
}
