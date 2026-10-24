<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The two funnel shapes the deck can take.
 *
 * The distinction is commercial, not cosmetic. GDC sells two very different
 * things: seats on a public certification course, bought by an individual who
 * very likely uses a personal mailbox, and corporate engagements bought by a
 * procurement or L&D function. Forcing one form on both either loses the
 * individual buyer (a work-email rule rejects the Gmail address they actually
 * use) or loses the qualifying data a corporate lead needs.
 *
 * So the deck is hybridised: the track decides which fields are asked for, how
 * many steps there are, and whether a corporate domain is enforced.
 */
enum FunnelTrack: string
{
    /**
     * A professional buying a seat for themselves. Short, low-friction, and
     * explicitly permissive about consumer email domains.
     */
    case Individual = 'individual';

    /**
     * A company buying training or consulting. Longer, qualifies team size and
     * budget authority, and requires a corporate email domain.
     */
    case Organisation = 'organisation';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Individual enrolment',
            self::Organisation => 'Corporate engagement',
        };
    }

    /**
     * Whether the email domain must be a corporate one.
     *
     * Only the organisation track enforces this. Applying it to individual
     * enrolment was rejecting the exact people the PMP course is sold to.
     */
    public function requiresCorporateEmail(): bool
    {
        return $this === self::Organisation;
    }

    /** Whether the deck asks for a company name and an organisation scope. */
    public function asksForOrganisation(): bool
    {
        return $this === self::Organisation;
    }

    /** Whether the deck asks for a team-size band. */
    public function asksForTeamSize(): bool
    {
        return $this === self::Organisation;
    }

    /** Whether the deck asks for the prospect's department or job title. */
    public function asksForDepartment(): bool
    {
        return $this === self::Organisation;
    }

    /**
     * Whether the deck asks for a preferred contact channel. Both tracks do:
     * an individual picks Zoom or Teams for a briefing, a corporate buyer picks
     * between a virtual session and an on-site visit.
     */
    public function asksForChannel(): bool
    {
        return true;
    }

    /** Whether a free-text scope note is collected. */
    public function asksForMessage(): bool
    {
        return $this === self::Organisation;
    }

    /** The team-size bands offered, smallest first. Empty for individuals. */
    public function teamSizeBands(): array
    {
        return match ($this) {
            self::Organisation => ['5-15', '16-50', '51+'],
            self::Individual => [],
        };
    }

    /** Human wording for the confirmation panel. */
    public function confirmationWording(): string
    {
        return match ($this) {
            self::Individual => 'A GDC enrolment advisor will confirm your seat and send the joining pack.',
            self::Organisation => 'A GDC Corporate Relations Manager will review your organisational profile and follow up.',
        };
    }
}
