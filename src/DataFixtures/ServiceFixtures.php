<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Service;
use App\Enum\FunnelTrack;
use App\Enum\Pillar;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * The published GDC offer, organised into the three pillars.
 *
 * This is seed content, not application logic: every field here is editable
 * from the admin afterwards, which is the point. It exists so a fresh install
 * has the real catalogue rather than placeholder lorem.
 */
final class ServiceFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $order = 0;

        foreach ($this->services() as $data) {
            $service = new Service();
            $service->setSlug($data['slug']);
            $service->setTitle($data['title']);
            $service->setTagline($data['tagline']);
            $service->setDescription($data['description']);
            $service->setPillar($data['pillar']);
            $service->setFunnelTrack($data['track']);
            $service->setCtaLabel($data['cta']);
            $service->setSortOrder($order += 10);
            $service->setFeatured($data['featured'] ?? false);

            if (isset($data['duration'])) {
                $service->setDuration($data['duration']);
            }
            if (isset($data['delivery'])) {
                $service->setDeliveryMethod($data['delivery']);
            }
            if (isset($data['pdus'])) {
                $service->setPduCount($data['pdus']);
            }
            if (isset($data['price'])) {
                $service->setPriceLabel($data['price']);
            }
            if (isset($data['priceNote'])) {
                $service->setPriceNote($data['priceNote']);
            }
            if (isset($data['minParticipants'])) {
                $service->setMinimumParticipants($data['minParticipants']);
            }

            $manager->persist($service);
        }

        $manager->flush();
    }

    /** @return list<array<string, mixed>> */
    private function services(): array
    {
        return [
            // ---- Pillar 1: Global Certification & Governance Frameworks ----
            [
                'slug' => 'pmp-exam-prep-master-class',
                'title' => 'PMP® Exam Prep Master Class',
                'tagline' => 'Fully authorized PMI-ATPI curriculum delivering 35 PDUs across 8 lessons.',
                'description' => "Delivered by a PMI Authorized Training Partner Instructor, this master class covers the complete PMP examination blueprint across 8 lessons and awards the 35 contact hours PMI requires for eligibility.\n\nCandidates work through scope, schedule, cost, risk and stakeholder practice against live portfolio scenarios rather than textbook examples, and leave with a study plan mapped to their own experience hours.\n\nEnrolment includes three months of PMPCitizen™ coaching, premium study materials, realistic exam simulators and assistance with application and exam scheduling.",
                'pillar' => Pillar::Certification,
                'track' => FunnelTrack::Individual,
                'cta' => 'Reserve your seat',
                'duration' => '8 lessons',
                'delivery' => 'Instructor-led',
                'pdus' => 35,
                'price' => 'USD 1,000',
                'priceNote' => 'Tariff for 2025',
                'featured' => true,
            ],
            [
                'slug' => 'construction-project-governance',
                'title' => 'Construction Project Governance',
                'tagline' => 'Specialised training for infrastructure, engineering and built-environment delivery.',
                'description' => "Infrastructure and construction programmes fail differently from software projects: long lead times, fixed physical constraints, layered subcontracting and regulatory sign-off.\n\nThis course adapts the governance and control techniques that work in those conditions, covering contract administration, milestone and payment governance, risk allocation across the supply chain, and the reporting cadence a client body needs to retain confidence.\n\nBuilt for project managers, engineers and client-side representatives working on capital works.",
                'pillar' => Pillar::Certification,
                'track' => FunnelTrack::Organisation,
                'cta' => 'Request the outline',
                'duration' => '3 days',
                'delivery' => 'Instructor-led',
                'minParticipants' => 5,
            ],
            [
                'slug' => 'pmbok-framework-alignment',
                'title' => 'PMBOK® Framework Alignment',
                'tagline' => 'Training and readiness tracking aligned to the latest PMI standards.',
                'description' => "For organisations whose internal project method has drifted from current PMI practice, or who need to demonstrate alignment for accreditation or client assurance purposes.\n\nWe map your existing process against the current PMBOK® framework, train the delivery teams on the gaps, and leave you with readiness tracking so progress is visible rather than assumed.\n\nSuitable for PMO leads and delivery functions running multiple concurrent projects.",
                'pillar' => Pillar::Certification,
                'track' => FunnelTrack::Organisation,
                'cta' => 'Book an alignment review',
                'duration' => '2 days',
                'delivery' => 'Instructor-led',
                'minParticipants' => 5,
            ],

            // ---- Pillar 2: Project Management Tool Mastery ----
            [
                'slug' => 'microsoft-project-practical-training',
                'title' => 'Microsoft Project Practical Training',
                'tagline' => 'Intensive, tool-focused workshops mapping schedules, resources and tracking.',
                'description' => "A hands-on workshop series that maps a corporate schedule onto Microsoft Project end to end: work breakdown, task dependencies, resource allocation, levelling, baselines and progress tracking.\n\nParticipants work on their own live project rather than a sample file, so they leave with a usable schedule and the competence to maintain it.\n\nCovers resource overallocation, critical path analysis and variance reporting against baseline.",
                'pillar' => Pillar::ToolMastery,
                'track' => FunnelTrack::Organisation,
                'cta' => 'Enquire about dates',
                'duration' => '3 days',
                'delivery' => 'Instructor-led',
                'minParticipants' => 5,
                'featured' => true,
            ],
            [
                'slug' => 'technology-enabled-solutions',
                'title' => 'Technology-Enabled Solutions',
                'tagline' => 'Custom workflow automation and business intelligence reporting dashboards.',
                'description' => "Delivery teams lose disproportionate time to reporting that could run itself. We build the automation.\n\nEngagements begin with a process review to identify the recurring manual work, then implement digital workflow automation and business intelligence dashboards on top of the systems you already run.\n\nEach implementation is scoped individually. Outcomes are measured against the hours removed from the delivery cycle.",
                'pillar' => Pillar::ToolMastery,
                'track' => FunnelTrack::Organisation,
                'cta' => 'Scope an automation',
                'delivery' => 'Project engagement',
            ],

            // ---- Pillar 3: Enterprise Strategy & Transformation ----
            [
                'slug' => 'corporate-training-leadership-development',
                'title' => 'Corporate Training & Leadership Development',
                'tagline' => 'High-impact team upskilling using the APPPV® model.',
                'description' => "Training built around the ValueBlueprint® framework, which aligns People, Processes and Purpose for Value (APPPV®) rather than treating skills development as an isolated event.\n\nProgrammes are designed around the organisation's actual delivery problems, delivered on your premises or ours, and structured so capability remains after the sessions end.\n\nA minimum of five participants is required so the cohort can work as a group on real material.",
                'pillar' => Pillar::Strategy,
                'track' => FunnelTrack::Organisation,
                'cta' => 'Request a quotation',
                'delivery' => 'Instructor-led',
                'minParticipants' => 5,
                'featured' => true,
            ],
            [
                'slug' => 'elite-business-consulting',
                'title' => 'Elite Business Consulting',
                'tagline' => 'Project management consulting, process optimization and strategic risk advisory.',
                'description' => "Structural consulting for organisations that need to turn strategy into execution: project management consulting, business process optimization, and strategic risk advisory.\n\nWe begin by analysing where execution breaks down, then streamline the affected operations and implement tailored solutions against measurable outcomes.\n\nEngagements span corporate enterprises, SMEs and public bodies. Success is measured through performance metrics and client feedback rather than deliverables alone.",
                'pillar' => Pillar::Strategy,
                'track' => FunnelTrack::Organisation,
                'cta' => 'Schedule a consultation',
                'delivery' => 'Advisory engagement',
            ],
        ];
    }
}
