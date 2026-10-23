<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CourseInquiry;
use App\Enum\LeadStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CourseInquiry> */
class CourseInquiryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CourseInquiry::class);
    }

    /**
     * Newest first. The dashboard renders this directly, so the ordering is
     * part of the contract rather than a display concern.
     *
     * @return list<CourseInquiry>
     */
    public function findLatest(int $limit = 100): array
    {
        return $this->createQueryBuilder("i")
            ->orderBy("i.createdAt", "DESC")
            ->addOrderBy("i.id", "DESC")
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** Leads that have not yet been actioned. */
    public function countPending(): int
    {
        return (int) $this->createQueryBuilder("i")
            ->select("COUNT(i.id)")
            ->andWhere("i.status = :new")
            ->setParameter("new", LeadStatus::New)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Leads with a confirmed consultative slot booked. */
    public function countScheduledBriefings(): int
    {
        return (int) $this->createQueryBuilder("i")
            ->select("COUNT(i.id)")
            ->andWhere("i.status = :active")
            ->setParameter("active", LeadStatus::ActiveBriefing)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Total pipeline valuation across every stored lead. Sums the stored value
     * so historical figures stay stable if tier pricing later changes.
     */
    public function totalPipelineValueUsd(): int
    {
        return (int) $this->createQueryBuilder("i")
            ->select("COALESCE(SUM(i.estimatedValueUsd), 0)")
            ->getQuery()
            ->getSingleScalarResult();
    }
}
