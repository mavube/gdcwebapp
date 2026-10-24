<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContentItem;
use App\Enum\ContentSection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContentItem>
 */
final class ContentItemRepository extends ServiceEntityRepository
{
    /**
     * Per-request memo of active rows, keyed by section value.
     *
     * The shell reads the same lists more than once — the header and the footer
     * both render the navigation — so without this a page would issue the same
     * query twice. A repository is a per-request service, so the memo cannot
     * outlive the request and serve stale rows.
     *
     * @var array<string, list<ContentItem>>
     */
    private array $activeMemo = [];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContentItem::class);
    }

    /**
     * Every active row for one section, in display order.
     *
     * @return list<ContentItem>
     */
    public function findActiveFor(ContentSection $section): array
    {
        return $this->activeMemo[$section->value] ??= $this->findBy(
            ['section' => $section, 'active' => true],
            ['sortOrder' => 'ASC', 'id' => 'ASC'],
        );
    }

    /**
     * Every active row, grouped by section, for one page render.
     *
     * One query for the whole page rather than one per list, matching how
     * {@see \App\Repository\ContentBlockRepository::findActiveKeyed()} avoids a
     * lookup per section.
     *
     * Sections with no active rows are absent from the map rather than present
     * as an empty list, so a template can test for the key and skip the section.
     *
     * @return array<string, list<ContentItem>> keyed by ContentSection value
     */
    public function findActiveGroupedBySection(): array
    {
        $grouped = [];

        foreach ($this->findBy(['active' => true], ['sortOrder' => 'ASC', 'id' => 'ASC']) as $item) {
            $section = $item->getSection();
            if ($section !== null) {
                $grouped[$section->value][] = $item;
            }
        }

        return $grouped;
    }

    public function countActiveFor(ContentSection $section): int
    {
        return $this->count(['section' => $section, 'active' => true]);
    }
}
