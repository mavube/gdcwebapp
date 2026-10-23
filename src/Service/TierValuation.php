<?php

declare(strict_types=1);

namespace App\Service;

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

    public function forTier(?IntentTier $tier): int
    {
        if ($tier === null) {
            return 0;
        }

        return self::DEFAULTS[$tier->value] ?? 0;
    }
}
