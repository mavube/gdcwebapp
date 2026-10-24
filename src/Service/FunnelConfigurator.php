<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Service;
use App\Enum\FunnelTrack;
use App\Repository\ServiceRepository;

/**
 * Builds the JSON configuration the conversion deck renders from.
 *
 * The deck is a single component; what differs between a PMP seat purchase and
 * a corporate consulting enquiry is the configuration, not the markup. Keeping
 * that decision here rather than in the template means the funnel shape can be
 * changed without touching HTML, and the same config can be asserted in tests.
 *
 * The output is deliberately a plain array: it is serialised straight to JSON
 * for the browser, so it must contain no objects or closures.
 */
final class FunnelConfigurator
{
    public function __construct(
        private readonly ServiceRepository $services,
    ) {
    }

    /**
     * The fields the deck can render, in the order they appear per step.
     *
     * Each entry declares when it applies so the configurator stays declarative:
     * "always", or gated on a track capability.
     *
     * @var list<array{name: string, label: string, type: string, autocomplete: string, placeholder: string, gate: string}>
     */
    private const FIELDS = [
        [
            'name' => 'fullName',
            'label' => 'Full name',
            'type' => 'text',
            'autocomplete' => 'name',
            'placeholder' => 'Jane Doe',
            'gate' => 'always',
        ],
        [
            'name' => 'companyName',
            'label' => 'Company name',
            'type' => 'text',
            'autocomplete' => 'organization',
            'placeholder' => 'Acme Industries Ltd',
            'gate' => 'organisation',
        ],
        [
            'name' => 'department',
            'label' => 'Department or job title',
            'type' => 'text',
            'autocomplete' => 'organization-title',
            'placeholder' => 'Head of Delivery',
            'gate' => 'department',
        ],
        [
            'name' => 'email',
            'label' => 'Work email address',
            'type' => 'email',
            'autocomplete' => 'email',
            'placeholder' => 'jane.doe@acme.com',
            'gate' => 'always',
        ],
        [
            'name' => 'phone',
            'label' => 'Phone number',
            'type' => 'tel',
            'autocomplete' => 'tel',
            'placeholder' => '+255 700 000 000',
            'gate' => 'always',
        ],
        [
            'name' => 'organizationScope',
            'label' => 'Organisation scope',
            'type' => 'text',
            'autocomplete' => 'off',
            'placeholder' => 'e.g. 500-1000 staff, 3 regions',
            'gate' => 'organisation',
        ],
    ];

    /**
     * Builds the deck's configuration.
     *
     * When no service is given the deck still lists every published offer, so
     * the deck shown on the home page is a real entry point for each one rather
     * than a generic three-tier fallback.
     */
    public function build(?Service $service = null): array
    {
        $track = $service?->getFunnelTrack() ?? FunnelTrack::Organisation;

        return [
            'track' => $track->value,
            'trackLabel' => $track->label(),
            'service' => $service === null ? null : [
                'slug' => $service->getSlug(),
                'title' => $service->getTitle(),
                'pillar' => $service->getPillar()?->value,
                'pillarLabel' => $service->getPillar()?->shortLabel(),
            ],
            'email' => [
                'corporateOnly' => $track->requiresCorporateEmail(),
                'label' => $track->requiresCorporateEmail() ? 'Work email address' : 'Email address',
                'hint' => $track->requiresCorporateEmail()
                    ? 'A corporate domain is required for this enquiry.'
                    : 'A personal address is fine for individual enrolment.',
            ],
            'steps' => $this->steps($track),
            'entries' => $this->entries(),
            // slug => track, so the deck can switch its shape the moment the
            // prospect picks a different offer, without a round trip.
            'tracks' => $this->tracks(),
            'copy' => $this->copy(),
        ];
    }

    /**
     * The per-track copy the deck swaps in when the selection changes.
     *
     * Kept server-side so the wording lives next to the rule that decides it.
     *
     * @return array<string, array<string, string>>
     */
    private function copy(): array
    {
        $copy = [];

        foreach (FunnelTrack::cases() as $track) {
            $steps = $this->steps($track);
            $fields = $steps[1];

            $copy[$track->value] = [
                'heading' => $fields['heading'],
                'body' => $fields['body'],
                'emailLabel' => $track->requiresCorporateEmail() ? 'Work email address' : 'Email address',
                'emailHint' => $track->requiresCorporateEmail()
                    ? 'A corporate domain is required for this enquiry.'
                    : 'A personal address is fine for individual enrolment.',
                'confirmation' => $track->confirmationWording(),
            ];
        }

        return $copy;
    }

    /**
     * Maps each published offer to its funnel track.
     *
     * @return array<string, string>
     */
    private function tracks(): array
    {
        $tracks = [];

        foreach ($this->services->findAllPublished() as $service) {
            $slug = $service->getSlug();
            if ($slug !== null) {
                $tracks[$slug] = $service->getFunnelTrack()->value;
            }
        }

        return $tracks;
    }

    /**
     * The step list. Each step carries the fields it renders, so the deck never
     * needs to know which track it is serving.
     *
     * @return list<array<string, mixed>>
     */
    private function steps(FunnelTrack $track): array
    {
        $fields = array_values(array_filter(
            self::FIELDS,
            fn (array $field): bool => $this->gateOpen($field['gate'], $track),
        ));

        $steps = [
            [
                'id' => 1,
                'key' => 'intent',
                'label' => 'Step 1 of '.($track === FunnelTrack::Individual ? '2' : '2').' · Select your objective',
                'heading' => 'How can GDC support your organisation?',
                'body' => 'Choose the track that matches your immediate priority.',
                'kind' => 'intent',
            ],
            [
                'id' => 2,
                'key' => 'details',
                'label' => 'Step 2 of 2 · Your details',
                'heading' => $track === FunnelTrack::Individual
                    ? 'Where should we send your confirmation?'
                    : 'Where should we send your quotation?',
                'body' => $track === FunnelTrack::Individual
                    ? 'All fields are required. We reply within one business day.'
                    : 'All fields are required. We reply to corporate addresses only.',
                'kind' => 'fields',
                'fields' => array_map(
                    fn (array $field): array => [
                        'name' => $field['name'],
                        'label' => $field['name'] === 'email' ? $this->emailLabel($track) : $field['label'],
                        'type' => $field['type'],
                        'autocomplete' => $field['autocomplete'],
                        'placeholder' => $field['placeholder'],
                        'required' => true,
                    ],
                    $fields,
                ),
                'teamSizes' => $track->teamSizeBands(),
                'channels' => $this->channels(),
            ],
        ];

        return $steps;
    }

    private function emailLabel(FunnelTrack $track): string
    {
        return $track->requiresCorporateEmail() ? 'Work email address' : 'Email address';
    }

    /**
     * Preferred contact channels. Both tracks use this, but the wording differs:
     * an individual books a briefing, a corporate buyer books a visit.
     *
     * @return list<array{value: string, label: string}>
     */
    private function channels(): array
    {
        return [
            ['value' => 'zoom', 'label' => 'Zoom'],
            ['value' => 'teams', 'label' => 'Microsoft Teams'],
            ['value' => 'onsite', 'label' => 'On site at Mikocheni B'],
        ];
    }

    private function gateOpen(string $gate, FunnelTrack $track): bool
    {
        return match ($gate) {
            'always' => true,
            'organisation' => $track->asksForOrganisation(),
            'department' => $track->asksForDepartment(),
            default => false,
        };
    }

    /**
     * The selectable offers shown in step 1, grouped by pillar.
     *
     * @return list<array<string, mixed>>
     */
    private function entries(): array
    {
        $grouped = [];

        foreach ($this->services->findAllPublished() as $service) {
            $pillar = $service->getPillar();
            if ($pillar === null) {
                continue;
            }

            $grouped[$pillar->value] ??= [
                'pillar' => $pillar->value,
                'label' => $pillar->label(),
                'blurb' => $pillar->blurb(),
                'services' => [],
            ];

            $grouped[$pillar->value]['services'][] = [
                'slug' => $service->getSlug(),
                'title' => $service->getTitle(),
                'tagline' => $service->getTagline(),
                'track' => $service->getFunnelTrack()->value,
                'featured' => $service->isFeatured(),
            ];
        }

        return array_values($grouped);
    }
}
