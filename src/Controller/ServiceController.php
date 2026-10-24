<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ContentBlockRepository;
use App\Repository\ServiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The public service detail pages.
 *
 * Each service renders its own funnel, whose shape is read from the service's
 * FunnelTrack. The deck itself is one component; what varies per service is the
 * configuration it is handed, not the markup.
 */
final class ServiceController extends AbstractController
{
    #[Route('/services', name: 'app_service_index', methods: ['GET'])]
    public function index(ServiceRepository $services, ContentBlockRepository $blocks): Response
    {
        return $this->render('service/index.html.twig', [
            'pillars' => \App\Enum\Pillar::cases(),
            'grouped' => $services->findPublishedGroupedByPillar(),
            'blocks' => $blocks->findActiveKeyed(),
        ]);
    }

    #[Route(
        '/services/{slug}',
        name: 'app_service_show',
        methods: ['GET'],
        requirements: ['slug' => '[a-z0-9-]+'],
    )]
    public function show(string $slug, ServiceRepository $services, ContentBlockRepository $blocks): Response
    {
        $service = $services->findOnePublishedBySlug($slug);

        if ($service === null) {
            throw $this->createNotFoundException('That service is not available.');
        }

        return $this->render('service/show.html.twig', [
            'service' => $service,
            'siblings' => $this->siblingsOf($service, $services),
            'blocks' => $blocks->findActiveKeyed(),
            // Scopes the conversion deck to this service, so its funnel track
            // decides which fields the prospect sees.
            'deckService' => $service,
        ]);
    }

    /**
     * The other published offers in the same pillar, for the cross-sell strip.
     *
     * @return list<\App\Entity\Service>
     */
    private function siblingsOf(\App\Entity\Service $service, ServiceRepository $services): array
    {
        return array_values(array_filter(
            $services->findAllPublished(),
            static fn (\App\Entity\Service $candidate): bool =>
                $candidate->getId() !== $service->getId()
                && $candidate->getPillar() === $service->getPillar(),
        ));
    }
}
