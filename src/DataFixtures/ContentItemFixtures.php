<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ContentItem;
use App\Enum\ContentSection;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Seeds the repeatable content rows.
 *
 * Two rules shape what is seeded here.
 *
 * First, everything is also editable from the admin, and that is the intent:
 * this fixture exists so a fresh install has a working page instead of blank
 * sections. No template depends on a row existing.
 *
 * Second, only claims GDC can substantiate are seeded. There are no client
 * testimonials on record, so "home.reviews" is left empty and its section stays
 * dormant rather than shipping invented endorsements; the same applies to the
 * portrait stack. The sections exist in the UI and are ready to fill.
 */
final class ContentItemFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $order = [];

        foreach ($this->rows() as $row) {
            $section = ContentSection::from($row['section']);

            $item = new ContentItem();
            $item->setSection($section);
            $item->setSortOrder($order[$row['section']] = ($order[$row['section']] ?? 0) + 10);

            foreach (['title', 'subtitle', 'body', 'image', 'imageAlt', 'linkLabel', 'linkTarget'] as $field) {
                if (isset($row[$field])) {
                    $item->{'set'.ucfirst($field)}($row[$field]);
                }
            }

            $manager->persist($item);
        }

        $manager->flush();
    }

    /**
     * @return list<array<string, string>>
     */
    private function rows(): array
    {
        return [
            // ---- Header and footer shell ----
            ['section' => 'site.nav', 'title' => 'Services', 'linkTarget' => 'route:app_service_index'],
            ['section' => 'site.nav', 'title' => 'Our approach', 'linkTarget' => '#method'],
            ['section' => 'site.nav', 'title' => 'Questions', 'linkTarget' => '#faq'],
            ['section' => 'site.nav', 'title' => 'Contact', 'linkTarget' => '#contact'],

            ['section' => 'site.footer.links', 'title' => 'Services', 'linkTarget' => 'route:app_service_index'],
            ['section' => 'site.footer.links', 'title' => 'Our approach', 'linkTarget' => '#method'],
            ['section' => 'site.footer.links', 'title' => 'Selected work', 'linkTarget' => '#work'],
            ['section' => 'site.footer.links', 'title' => 'Questions', 'linkTarget' => '#faq'],
            ['section' => 'site.footer.links', 'title' => 'Contact', 'linkTarget' => '#contact'],

            // ---- Contact channels: the site's real details ----
            [
                'section' => 'site.contact',
                'title' => 'Visit',
                'subtitle' => 'Mikocheni B, Light Industrial Street, House #8, Dar es Salaam, Tanzania',
            ],
            [
                'section' => 'site.contact',
                'title' => 'Call',
                'subtitle' => '+255 753 000 360',
                'linkTarget' => 'tel:+255753000360',
            ],
            [
                'section' => 'site.contact',
                'title' => 'Email',
                'subtitle' => 'info@gdc.co.tz',
                'linkTarget' => 'mailto:info@gdc.co.tz',
            ],

            // ---- Legal line ----
            [
                'section' => 'site.legal',
                'body' => 'PMI, PMP, PMBOK and ATP are registered marks of the Project Management Institute, Inc.',
            ],

            // ---- Hero credibility row ----
            // The standards the practice works to, not client logos: no client
            // has agreed to be named, so none is.
            ['section' => 'home.hero.trust', 'title' => 'PMI Authorized Training Partner'],
            ['section' => 'home.hero.trust', 'title' => 'APPPV® aligned'],
            ['section' => 'home.hero.trust', 'title' => 'Dar es Salaam'],

            // ---- Hero figures ----
            // Each of these is a fact about the catalogue or the practice, not a
            // marketing claim: the first is the count of published programmes.
            ['section' => 'home.hero.stats', 'title' => '07', 'subtitle' => 'Programmes published'],
            ['section' => 'home.hero.stats', 'title' => '03', 'subtitle' => 'Commercial pillars'],
            ['section' => 'home.hero.stats', 'title' => '35', 'subtitle' => 'PMP contact hours'],
            ['section' => 'home.hero.stats', 'title' => '01', 'subtitle' => 'Business day to reply'],

            // ---- Selected engagements ----
            // No client engagement is published yet, so no tile claims one. The
            // rows below describe the work the practice does; the images are
            // added through the admin once a photograph is licensed.
            [
                'section' => 'home.work',
                'title' => 'Certification programmes',
                'subtitle' => 'Certification & Governance',
                'body' => 'PMP® Exam Prep and associated corporate development paths, delivered by practising programme leaders.',
                'linkTarget' => 'certification',
            ],
            [
                'section' => 'home.work',
                'title' => 'Tool and platform mastery',
                'subtitle' => 'Tool Mastery',
                'body' => 'Hands-on Microsoft Project and delivery-tooling training, taught against live project scenarios.',
                'linkTarget' => 'tool-mastery',
            ],
            [
                'section' => 'home.work',
                'title' => 'Enterprise advisory',
                'subtitle' => 'Strategy & Transformation',
                'body' => 'Organisational assessment and sequenced transformation work under the APPPV® model.',
                'linkTarget' => 'strategy',
            ],

            // ---- The numbered offer ----
            // The four rows the design's "our services" section carries. Each
            // links to the pillar or programme behind it rather than to a page
            // that does not exist.
            [
                'section' => 'home.services',
                'title' => 'Certification & governance frameworks',
                'body' => 'Methodologies, official credentials and industry governance standards, including the PMP® Exam Prep Master Class.',
                'linkTarget' => 'certification',
            ],
            [
                'section' => 'home.services',
                'title' => 'Project management tool mastery',
                'body' => 'Tactical, hands-on software execution and technical proficiency with the tools delivery teams use.',
                'linkTarget' => 'tool-mastery',
            ],
            [
                'section' => 'home.services',
                'title' => 'Enterprise strategy & transformation',
                'body' => 'Organisational shifts and large-scale consulting, sequenced so capability compounds.',
                'linkTarget' => 'strategy',
            ],
            [
                'section' => 'home.services',
                'title' => 'Support and development',
                'body' => 'Ongoing mentorship and capability support after the programme ends, so the change holds.',
                'linkTarget' => 'route:app_service_index',
            ],

            // ---- Method panel ----
            // The tick list states how the practice works, which it can
            // substantiate. The portrait stack is deliberately empty.
            ['section' => 'home.team.checks', 'body' => 'Assessment across people, process and purpose'],
            ['section' => 'home.team.checks', 'body' => 'Delivery by practising consultants, not career trainers'],
            ['section' => 'home.team.checks', 'body' => 'Support that continues after the programme ends'],

            // ---- Questions ----
            [
                'section' => 'home.faq',
                'title' => 'How long does the PMP® Exam Prep Master Class take?',
                'body' => 'It is a five-day intensive that awards the 35 contact hours PMI requires for eligibility. We also run it as a corporate cohort, where the dates and the case material are set around your project.',
            ],
            [
                'section' => 'home.faq',
                'title' => 'Do you train teams on site, or only run open cohorts?',
                'body' => 'Both. Most of our work is corporate cohorts delivered on site or at a venue you choose, and we set the case material around a project the team is actually running.',
            ],
            [
                'section' => 'home.faq',
                'title' => 'Who delivers the training?',
                'body' => 'Practising consultants who run projects themselves, delivered under our PMI Authorized Training Partner authorisation. The instructor for a cohort is named before you commit to it.',
            ],
            [
                'section' => 'home.faq',
                'title' => 'What happens after we send an enquiry?',
                'body' => 'A consultant reviews it and replies within one business day, usually with a short call to scope the requirement before we propose a programme.',
            ],
            [
                'section' => 'home.faq',
                'title' => 'Can you work with our existing processes rather than replacing them?',
                'body' => 'Yes. The APPPV® model assesses what you already have and sequences changes against it, so the work builds on your current practice instead of resetting it.',
            ],

            // ---- Programme page: solution cards ----
            // Only the lines of work the practice actually offers. The previous
            // template named client projects and product marks it could not
            // substantiate; those are gone.
            [
                'section' => 'training.solutions',
                'subtitle' => 'Certification & Governance',
                'title' => 'Certification pathways',
                'body' => 'Official credentials and the governance standards that sit around them, delivered by practising programme leaders.',
                'linkLabel' => 'See the catalogue',
                'linkTarget' => 'route:app_service_index',
            ],
            [
                'section' => 'training.solutions',
                'subtitle' => 'Tool Mastery',
                'title' => 'Delivery tooling',
                'body' => 'Hands-on software execution so the methods this programme teaches survive contact with the tools your teams use.',
                'linkLabel' => 'See the catalogue',
                'linkTarget' => 'route:app_service_index',
            ],
            [
                'section' => 'training.solutions',
                'subtitle' => 'Strategy & Transformation',
                'title' => 'Enterprise advisory',
                'body' => 'Assessment and sequenced transformation work, for organisations changing how projects are run rather than only training people.',
                'linkLabel' => 'See the catalogue',
                'linkTarget' => 'route:app_service_index',
            ],
        ];
    }
}
