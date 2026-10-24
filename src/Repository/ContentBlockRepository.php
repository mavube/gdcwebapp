<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContentBlock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContentBlock>
 */
final class ContentBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContentBlock::class);
    }

    /**
     * Every active block, keyed by its key.
     *
     * Returned as one map so a page can be rendered from a single query rather
     * than one lookup per section.
     *
     * @return array<string, ContentBlock>
     */
    public function findActiveKeyed(): array
    {
        $keyed = [];

        foreach ($this->findBy(['active' => true], ['sortOrder' => 'ASC']) as $block) {
            $key = $block->getBlockKey();
            if ($key !== null) {
                $keyed[$key] = $block;
            }
        }

        return $keyed;
    }

    public function findOneByKey(string $key): ?ContentBlock
    {
        return $this->findOneBy(['blockKey' => $key, 'active' => true]);
    }
}
