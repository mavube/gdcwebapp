<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The three commercial pillars GDC organises its offer around.
 *
 * The stored value is the stable identifier used on the wire and in the
 * database; the label and blurb are presentation and may be reworded without a
 * migration. Ordering is the order they appear on the site.
 */
enum Pillar: string
{
    case Certification = 'certification';
    case ToolMastery = 'tool-mastery';
    case Strategy = 'strategy';

    public function label(): string
    {
        return match ($this) {
            self::Certification => 'Global Certification & Governance Frameworks',
            self::ToolMastery => 'Project Management Tool Mastery',
            self::Strategy => 'Enterprise Strategy & Transformation',
        };
    }

    /**
     * The short form used in navigation, cards and lead records where the full
     * label would wrap or crowd the layout.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Certification => 'Certification & Governance',
            self::ToolMastery => 'Tool Mastery',
            self::Strategy => 'Strategy & Transformation',
        };
    }

    public function blurb(): string
    {
        return match ($this) {
            self::Certification => 'Methodologies, official credentials and industry governance standards.',
            self::ToolMastery => 'Tactical, hands-on software execution and technical proficiency.',
            self::Strategy => 'Organisational shifts and large-scale consulting.',
        };
    }

    /**
     * Whether leads in this pillar are organisations rather than individuals.
     *
     * This is the pivot the funnel keys off: a corporate track asks for a
     * company, a team size and a work email address, while an individual track
     * (a professional buying their own PMP seat) must accept a personal mailbox.
     */
    public function isOrganisational(): bool
    {
        return $this !== self::Certification;
    }

    public function accent(): string
    {
        return match ($this) {
            self::Certification => 'brand',
            self::ToolMastery => 'accent',
            self::Strategy => 'ink',
        };
    }
}
