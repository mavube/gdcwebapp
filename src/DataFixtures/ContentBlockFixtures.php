<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ContentBlock;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Seeds the site's editable copy.
 *
 * Every string here is also editable from the admin, and that is the intent:
 * this fixture only exists so a fresh install has real content instead of
 * blank sections. Nothing in a template should depend on a block existing.
 */
final class ContentBlockFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $order = 0;

        foreach ($this->blocks() as $data) {
            $block = new ContentBlock();
            $block->setBlockKey($data['key']);
            $block->setLabel($data['label']);
            $block->setSortOrder($order += 10);

            if (isset($data['eyebrow'])) {
                $block->setEyebrow($data['eyebrow']);
            }
            if (isset($data['heading'])) {
                $block->setHeading($data['heading']);
            }
            if (isset($data['headingAccent'])) {
                $block->setHeadingAccent($data['headingAccent']);
            }
            if (isset($data['body'])) {
                $block->setBody($data['body']);
            }
            if (isset($data['cta'])) {
                $block->setCtaLabel($data['cta']);
            }
            if (isset($data['target'])) {
                $block->setCtaTarget($data['target']);
            }
            if (isset($data['secondaryCta'])) {
                $block->setSecondaryCtaLabel($data['secondaryCta']);
            }
            if (isset($data['secondaryTarget'])) {
                $block->setSecondaryCtaTarget($data['secondaryTarget']);
            }
            if (isset($data['image'])) {
                $block->setImage($data['image']);
            }
            if (isset($data['imageAlt'])) {
                $block->setImageAlt($data['imageAlt']);
            }

            $manager->persist($block);
        }

        $manager->flush();
    }

    /** @return list<array<string, string>> */
    private function blocks(): array
    {
        return [
            // ---- Shell ----
            [
                'key' => 'site.header.cta',
                'label' => 'Site · Header button',
                'cta' => 'Request briefing',
                'target' => 'funnel',
            ],
            [
                'key' => 'site.footer.blurb',
                'label' => 'Site · Footer blurb',
                'body' => 'Empowering businesses and professionals with training, business solutions and strategic consulting, aligning People, Processes and Purpose for Value (APPPV®).',
            ],

            // ---- Home: hero ----
            [
                'key' => 'home.hero',
                'label' => 'Home · Hero',
                'eyebrow' => 'Dar es Salaam, Tanzania',
                'heading' => 'Certification. Tool mastery.',
                'headingAccent' => 'Enterprise strategy.',
                'body' => 'Global Dynamics Consulting equips organisations and professionals to deliver projects that hold under pressure. We align People, Processes and Purpose for Value (APPPV®) rather than treating training as a standalone event.',
                'cta' => 'Request a briefing',
                'target' => 'funnel',
                'secondaryCta' => 'Browse the catalogue',
                'secondaryTarget' => '#solutions',
            ],

            // ---- Home: selected engagements ----
            [
                'key' => 'home.work',
                'label' => 'Home · Selected engagements',
                'eyebrow' => 'What we do',
                'heading' => 'Capability built around the work you actually run',
                'body' => 'Three lines of work, each delivered by consultants who practise it. The tiles below are filled with published engagements as they are released.',
            ],

            // ---- Home: the numbered offer ----
            [
                'key' => 'home.services',
                'label' => 'Home · Numbered offer',
                'heading' => 'What we deliver',
                'body' => 'Every offer is delivered by practising consultants and carries its own enquiry route, so you reach the right team from the first click.',
                'cta' => 'See the full catalogue',
                'target' => '#solutions',
            ],

            // ---- Home: the catalogue ----
            [
                'key' => 'home.pillars',
                'label' => 'Home · Catalogue section',
                'eyebrow' => 'The catalogue',
                'heading' => 'Three pillars, seven programmes',
                'body' => 'Choose the pillar that fits. Each programme below has its own enquiry route and a named consultant who owns the response.',
            ],

            // ---- Home: method panel ----
            [
                'key' => 'home.method',
                'label' => 'Home · Method panel',
                'eyebrow' => 'The APPPV® model',
                'heading' => 'Aligning People, Processes and Purpose for Value',
                'body' => "Most improvement efforts stall because they fix one part of the system. We assess all three, then sequence the work so capability compounds instead of resetting with each initiative.",
                // The credential line: the figure and what it counts. Rendered
                // only when both are present, so a bare number never appears.
                'cta' => '35',
                'target' => 'contact hours awarded on the PMP® path',
            ],

            // ---- Home: questions ----
            [
                'key' => 'home.faq',
                'label' => 'Home · Questions',
                'eyebrow' => 'Before you enquire',
                'heading' => 'Questions we are asked most',
                'body' => 'The answers below cover how we work and what happens after you get in touch. If yours is not here, ask it in the enquiry and a consultant will answer directly.',
            ],

            // ---- Home: reviews ----
            // Seeded so the heading is ready, but the section only renders once
            // review rows exist. There are no client testimonials on record and
            // inventing them is not acceptable, so the block ships without any.
            [
                'key' => 'home.reviews',
                'label' => 'Home · Client reviews',
                'eyebrow' => 'Client feedback',
                'heading' => 'What clients say',
                'body' => 'Reviews published in the admin appear here as a scrolling wall.',
            ],

            // ---- Home: closing call to action ----
            [
                'key' => 'home.contact',
                'label' => 'Home · Closing call to action',
                'eyebrow' => 'Start here',
                'heading' => 'Tell us what you are trying to deliver',
                'body' => 'Send us the requirement and a consultant will reply within one business day, usually to scope it on a short call before proposing a programme.',
                'cta' => 'Request a briefing',
                'target' => 'funnel',
                'secondaryCta' => 'See the catalogue',
                'secondaryTarget' => '#solutions',
            ],

            // ---- Service detail page ----
            // The labels around the record's own fields. The title, tagline and
            // description come from the service record, so they are not repeated.
            [
                'key' => 'service.overview',
                'label' => 'Service · Overview panel',
                'eyebrow' => 'Overview',
                'heading' => 'What this programme covers',
                'secondaryCta' => 'Read the overview',
                'secondaryTarget' => '#overview',
            ],
            [
                'key' => 'service.siblings',
                'label' => 'Service · Related programmes',
                'heading' => 'Related programmes',
            ],
            [
                'key' => 'service.closing',
                'label' => 'Service · Closing band',
                'eyebrow' => 'Next step',
                'body' => 'Ready to talk through this programme?',
            ],

            // ---- Services index ----
            [
                'key' => 'services.intro',
                'label' => 'Services · Intro',
                'eyebrow' => 'Our services',
                'heading' => 'Programmes, tools and advisory',
                'body' => 'Choose a pillar to see what we deliver. Each programme has its own enquiry route and a named consultant who owns the response.',
            ],

            // ---- Programme page ----
            // The hero's framing line and its trailing headline phrase. The
            // programme's own title comes from the record, so it is not repeated
            // here; this holds only the words that wrap around it.
            [
                'key' => 'training.hero',
                'label' => 'Programme · Hero',
                'eyebrow' => 'Global Dynamics Consulting • Executive Development',
                'headingAccent' => 'Certification & Strategy',
            ],

            // The enquiry panel's copy.
            [
                'key' => 'training.inquiry',
                'label' => 'Programme · Inquiry panel',
                'eyebrow' => 'Next step',
                'heading' => 'Request a quotation or book a meeting',
                'body' => 'Tell us what your team needs. A consultant will respond within one business day.',
            ],

            // The heading for the solution cards. The cards themselves are
            // "training.solutions" rows in the item fixtures.
            [
                'key' => 'training.solutions',
                'label' => 'Programme · Solution cards heading',
                'eyebrow' => 'Related capability',
                'heading' => 'Where this programme connects',
                'body' => 'The programme sits inside a wider practice. These are the lines of work it draws on and feeds.',
            ],
        ];
    }
}
