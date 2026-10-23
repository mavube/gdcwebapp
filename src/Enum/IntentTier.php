<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The three corporate deliverables offered by the conversion deck.
 *
 * The stored value is the stable wire/database identifier. The label is the
 * client-facing wording and may change without a migration.
 */
enum IntentTier: string
{
    case ValueBlueprint = 'value-blueprint';
    case Credentialing = 'credentialing';
    case Mentorship = 'mentorship';

    public function label(): string
    {
        return match ($this) {
            self::ValueBlueprint => 'Enterprise Process Optimization & Strategy Briefing (ValueBlueprint™ Alignment)',
            self::Credentialing => 'Professional Credentialing Track (PMP / CAPM / MS Project Programs)',
            self::Mentorship => 'Executive Mentorship Framework Enrollment (PMPCitizen™ Lifecycle Support)',
        };
    }

    /** Short form for the telemetry grid, where the full label would wrap badly. */
    public function shortLabel(): string
    {
        return match ($this) {
            self::ValueBlueprint => 'ValueBlueprint™',
            self::Credentialing => 'Credentialing',
            self::Mentorship => 'Mentorship',
        };
    }
}
