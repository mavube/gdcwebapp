<?php

declare(strict_types=1);

namespace App\Enum;

/** Lifecycle of an inbound lead, surfaced as a badge in the telemetry grid. */
enum LeadStatus: string
{
    case New = 'new';
    case ActiveBriefing = 'active-briefing';
    case Converted = 'converted';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::ActiveBriefing => 'Active Briefing',
            self::Converted => 'Converted Partnership',
        };
    }

    /**
     * Tailwind classes for the badge. Kept here so the palette cannot drift
     * between the dashboard template and any future export view.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::New => 'border-sky-400/30 bg-sky-400/10 text-sky-300',
            self::ActiveBriefing => 'border-amber-400/30 bg-amber-400/10 text-amber-300',
            self::Converted => 'border-emerald-400/30 bg-emerald-400/10 text-emerald-300',
        };
    }
}
