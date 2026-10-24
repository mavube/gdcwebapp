<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Service;
use App\Enum\IntentTier;

/**
 * Maps an intent tier to a pipeline valuation in whole USD.
 *
 * Kept as a service rather than a constant on the enum so pricing can be
 * changed in one place, or later moved to configuration, without touching
 * domain types. Values are indicative contract sizes used for pipeline
 * reporting, not quotes.
 */
final class TierValuation
{
    /** @var array<string, int> */
    private const DEFAULTS = [
        IntentTier::ValueBlueprint->value => 48_000,
        IntentTier::Credentialing->value => 18_500,
        IntentTier::Mentorship->value => 12_000,
    ];

    /**
     * Indicative pipeline values by pillar, used when a lead arrives through a
     * service funnel rather than the legacy three-tier deck.
     *
     * These are reporting figures, not quotes: the catalogue shows a real price
     * only for the individually-priced PMP seat.
     *
     * @var array<string, int>
     */
    private const BY_PILLAR = [
        'certification' => 18_500,
        'tool-mastery' => 24_000,
        'strategy' => 48_000,
    ];

    public function forTier(?IntentTier $tier): int
    {
        if ($tier === null) {
            return 0;
        }

        return self::DEFAULTS[$tier->value] ?? 0;
    }

    /**
     * The indicative value of a lead from a service funnel.
     *
     * A featured offer is weighted up: it is the flagship of its pillar and
     * historically converts at a higher contract size.
     */
    public function forService(?Service $service): int
    {
        if ($service === null || $service->getPillar() === null) {
            return 0;
        }

        $base = self::BY_PILLAR[$service->getPillar()->value] ?? 0;

        return $service->isFeatured() ? (int) round($base * 1.25) : $base;
    }
}
