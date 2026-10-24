<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Service;
use App\Enum\Pillar;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Service>
 */
final class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    /**
     * Every published offer, ordered for display.
     *
     * Ordering is by pillar position first so the three pillars stay contiguous
     * on the home page, then by the editor's own sort order.
     *
     * @return list<Service>
     */
    public function findAllPublished(): array
    {
        $services = $this->findBy(['active' => true], ['sortOrder' => 'ASC', 'title' => 'ASC']);

        usort($services, static function (Service $a, Service $b): int {
            $pillar = array_search($a->getPillar(), Pillar::cases(), true)
                <=> array_search($b->getPillar(), Pillar::cases(), true);

            return $pillar !== 0 ? $pillar : $a->getSortOrder() <=> $b->getSortOrder();
        });

        return $services;
    }

    /**
     * Published offers grouped by pillar, for the home page matrix.
     *
     * Pillars with nothing published are omitted rather than rendered empty, so
     * an editor can take a whole pillar offline without leaving a gap.
     *
     * @return array<string, list<Service>> keyed by Pillar value
     */
    public function findPublishedGroupedByPillar(): array
    {
        $grouped = [];

        foreach ($this->findAllPublished() as $service) {
            $grouped[$service->getPillar()->value][] = $service;
        }

        return $grouped;
    }

    public function findOnePublishedBySlug(string $slug): ?Service
    {
        return $this->findOneBy(['slug' => $slug, 'active' => true]);
    }

    public function countPublished(): int
    {
        return $this->count(['active' => true]);
    }
}
