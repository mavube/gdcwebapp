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
            if (isset($data['body'])) {
                $block->setBody($data['body']);
            }
            if (isset($data['cta'])) {
                $block->setCtaLabel($data['cta']);
            }
            if (isset($data['target'])) {
                $block->setCtaTarget($data['target']);
            }

            $manager->persist($block);
        }

        $manager->flush();
    }

    /** @return list<array<string, string>> */
    private function blocks(): array
    {
        return [
            [
                'key' => 'home.hero',
                'label' => 'Home · Hero',
                'eyebrow' => 'Dar es Salaam, Tanzania',
                'heading' => 'Certification. Tool mastery. Enterprise strategy.',
                'body' => "Global Dynamics Consulting equips organisations and professionals to deliver projects that hold under pressure. We align People, Processes and Purpose for Value (APPPV®) rather than treating training as a standalone event.",
                'cta' => 'Request a briefing',
                'target' => 'funnel',
            ],
            [
                'key' => 'home.pillars',
                'label' => 'Home · Pillars section',
                'eyebrow' => 'What we do',
                'heading' => 'Three pillars, seven programmes',
                'body' => 'Every offer below is delivered by practising consultants and carries its own enquiry route, so you reach the right team from the first click.',
            ],
            [
                'key' => 'home.approach',
                'label' => 'Home · Approach section',
                'eyebrow' => 'The APPPV® model',
                'heading' => 'Aligning People, Processes and Purpose for Value',
                'body' => "Most improvement efforts stall because they fix one part of the system. We assess all three, then sequence the work so capability compounds instead of resetting with each initiative.",
            ],
            [
                'key' => 'home.credentials',
                'label' => 'Home · Credentials band',
                'eyebrow' => 'Authorisation',
                'heading' => 'Delivered by PMI Authorized Training Partner instructors',
                'body' => 'Our PMP® Exam Prep Master Class awards the 35 contact hours PMI requires for eligibility, taught by an ATP-authorised instructor.',
            ],
            [
                'key' => 'home.contact',
                'label' => 'Home · Contact section',
                'eyebrow' => 'Contact',
                'heading' => 'Speak to a consultant',
                'body' => "Mikocheni B, Light Industrial Street, House #8, Dar es Salaam. We reply to every corporate enquiry within one business day.",
                'cta' => 'Request a briefing',
                'target' => 'funnel',
            ],
            [
                'key' => 'services.intro',
                'label' => 'Services · Intro',
                'eyebrow' => 'Our services',
                'heading' => 'Programmes, tools and advisory',
                'body' => 'Choose a pillar to see what we deliver. Each programme has its own enquiry route and a named consultant who owns the response.',
            ],
        ];
    }
}
