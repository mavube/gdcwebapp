<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\TrainingProgram;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class TrainingProgramFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $programs = [
            [
                'slug' => 'pmp-certification',
                'title' => 'PMP',
                'tagline' => 'The gold standard in project management credentialing, rebuilt for delivery leaders who carry real portfolios.',
                'description' => "A five-day intensive covering the full PMP examination blueprint.\n\nScope, schedule, cost and risk are taught against live portfolio scenarios rather than textbook examples. Candidates leave with a study plan mapped to their own experience hours.",
                'duration' => '5 days',
                'deliveryMethod' => 'Instructor-led',
                'pduCount' => 35,
            ],
            [
                'slug' => 'agile-delivery-leadership',
                'title' => 'Agile Delivery Leadership',
                'tagline' => 'Scaling agile practice without losing the discipline that makes governance work.',
                'description' => "For leaders running multiple delivery streams who need agile to coexist with formal reporting.\n\nCovers portfolio-level flow metrics, dependency management and the governance conversations that keep executives informed.",
                'duration' => '3 days',
                'deliveryMethod' => 'Instructor-led',
                'pduCount' => 21,
            ],
            [
                'slug' => 'value-blueprint-automation',
                'title' => 'ValueBlueprint Automation',
                'tagline' => 'Turning recurring programme reporting into systems that run themselves.',
                'description' => "A hands-on workshop on automating the reporting overhead that consumes delivery teams.\n\nParticipants map one of their own recurring reports and leave with a working automation blueprint.",
                'duration' => '2 days',
                'deliveryMethod' => 'Instructor-led',
                'pduCount' => 14,
            ],
        ];

        foreach ($programs as $data) {
            $program = new TrainingProgram();
            $program->setSlug($data['slug']);
            $program->setTitle($data['title']);
            $program->setTagline($data['tagline']);
            $program->setDescription($data['description']);
            $program->setDuration($data['duration']);
            $program->setDeliveryMethod($data['deliveryMethod']);
            $program->setPduCount($data['pduCount']);
            $manager->persist($program);
        }

        $manager->flush();
    }
}
