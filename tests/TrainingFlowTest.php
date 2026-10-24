<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\ContentBlock;
use App\Entity\ContentItem;
use App\Entity\CourseInquiry;
use App\Entity\TrainingProgram;
use App\Enum\ContentSection;
use App\Repository\TrainingProgramRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class TrainingFlowTest extends WebTestCase
{
    /**
     * Boots the kernel, then clears the rate limiter pool. The limiter stores
     * counters in a cache pool that survives between tests, so each test must
     * start from a known quota. The kernel must not be booted before createClient().
     */
    private function client(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = self::createClient();
        self::getContainer()->get('cache.rate_limiter')->clear();

        return $client;
    }

    private function seedProgram(string $slug = 'pmp-certification'): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();

        if ($em->getRepository(TrainingProgram::class)->findOneBy(['slug' => $slug]) !== null) {
            return;
        }

        $p = new TrainingProgram();
        $p->setSlug($slug);
        $p->setTitle('PMP');
        $p->setTagline('Executive certification for delivery leaders.');
        $p->setDescription('Five-day intensive.');
        $p->setDuration('5 days');
        $p->setDeliveryMethod('Instructor-led');
        $p->setPduCount(35);
        $em->persist($p);
        $em->flush();
    }

    /**
     * The home hero's copy is editable content, so the test supplies its own
     * rather than depending on what the fixtures happen to hold. Asserting on
     * the fixture's wording would make this test fail for the right reason
     * whenever an editor reworded the hero.
     */
    public function testHomePageRenders(): void
    {
        $client = $this->client();
        $this->seedProgram();
        $this->seedHeroHeading('Certification. Tool mastery. Enterprise strategy.');

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Certification');
    }

    private function seedHeroHeading(string $heading): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $block = $em->getRepository(ContentBlock::class)->findOneBy(['blockKey' => 'home.hero']);

        if ($block === null) {
            $block = new ContentBlock();
            $block->setBlockKey('home.hero');
            $block->setLabel('Home · Hero');
            $em->persist($block);
        }

        $block->setActive(true);
        $block->setHeading($heading);
        $em->flush();
    }

    /**
     * The hero's framing line and headline suffix are editable content, so the
     * test seeds them rather than relying on the fixtures. The programme's own
     * title still comes from its record.
     */
    private function seedTrainingHero(): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $block = $em->getRepository(ContentBlock::class)->findOneBy(['blockKey' => 'training.hero']);

        if ($block === null) {
            $block = new ContentBlock();
            $block->setBlockKey('training.hero');
            $block->setLabel('Programme · Hero');
            $em->persist($block);
        }

        $block->setActive(true);
        $block->setHeadingAccent('Certification & Strategy');
        $em->flush();
    }

    public function testTrainingPageRendersHeroAndBento(): void
    {
        $client = $this->client();
        $this->seedProgram();
        $this->seedSolutionCards(4);
        $this->seedTrainingHero();

        $client->request('GET', '/training/pmp-certification');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('h1');
        self::assertSelectorTextContains('h1', 'PMP Certification');
        // The card grid renders one card per seeded row.
        self::assertSelectorCount(4, '#solutions article');
        // Inquiry form present.
        self::assertSelectorExists('form[name="course_inquiry"]');
    }

    /**
     * The solution cards are editable rows, so the test seeds its own rather
     * than depending on what the fixtures hold. The section renders nothing
     * when it has no rows, which is asserted separately below.
     */
    private function seedSolutionCards(int $count): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();

        $em->createQuery('DELETE FROM ' . ContentItem::class . ' i WHERE i.section = :section')
            ->setParameter('section', ContentSection::TrainingSolutions)
            ->execute();

        for ($i = 1; $i <= $count; $i++) {
            $item = new ContentItem();
            $item->setSection(ContentSection::TrainingSolutions);
            $item->setSortOrder($i * 10);
            $item->setTitle('Seeded solution card ' . $i);
            $item->setBody('Seeded solution body ' . $i . '.');
            $em->persist($item);
        }

        $em->flush();
    }

    /**
     * The section is content-driven: with no rows there is no heading and no
     * card, rather than an empty grid under a title.
     */
    public function testTrainingSolutionsSectionIsAbsentWithoutRows(): void
    {
        $client = $this->client();
        $this->seedProgram();
        $this->seedSolutionCards(0);

        $client->request('GET', '/training/pmp-certification');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#solutions');
    }

    public function testUnknownSlugReturns404(): void
    {
        $client = $this->client();
        $this->seedProgram();

        $client->request('GET', '/training/does-not-exist');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testInvalidSlugCharactersDoNotMatchRoute(): void
    {
        $client = $this->client();
        $this->seedProgram();

        $client->request('GET', '/training/BAD_Slug!');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testSqlInjectionAttemptReturns404(): void
    {
        $client = $this->client();
        $this->seedProgram();

        $client->request('GET', "/training/' OR '1'='1");
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testXssPayloadIsEscapedInOutput(): void
    {
        $client = $this->client();
        $this->seedProgram();

        $em = self::getContainer()->get('doctrine')->getManager();
        $program = $em->getRepository(TrainingProgram::class)->findOneBy(['slug' => 'pmp-certification']);
        $program->setTitle('<script>alert("xss")</script>');
        $program->setDescription('<img src=x onerror=alert(1)>');
        $em->flush();

        $client->request('GET', '/training/pmp-certification');
        $html = (string) $client->getResponse()->getContent();

        self::assertStringNotContainsString('<script>alert', $html, 'Raw script tag leaked into output');
        self::assertStringNotContainsString('<img src=x', $html, 'Raw img tag leaked into output');
        self::assertStringContainsString('&lt;script&gt;alert', $html, 'Expected escaped script tag');

        // Restore so other tests see clean data.
        $program->setTitle('PMP');
        $program->setDescription('Five-day intensive.');
        $em->flush();
    }

    public function testInquirySubmissionPersists(): void
    {
        $client = $this->client();
        $this->seedProgram();

        $crawler = $client->request('GET', '/training/pmp-certification');
        $form = $crawler->selectButton('Send request')->form([
            'course_inquiry[fullName]' => 'Jane Doe',
            'course_inquiry[companyName]' => 'Acme Ltd',
            'course_inquiry[email]' => 'jane@acme.com',
            'course_inquiry[phone]' => '+254700000000',
            'course_inquiry[inquiryType]' => CourseInquiry::TYPE_QUOTATION,
        ]);

        $client->submit($form);
        self::assertResponseRedirects('/training/pmp-certification');

        $em = self::getContainer()->get('doctrine')->getManager();
        $saved = $em->getRepository(CourseInquiry::class)->findOneBy(['email' => 'jane@acme.com']);
        self::assertNotNull($saved, 'Inquiry was not persisted');
        self::assertSame('Jane Doe', $saved->getFullName());
        self::assertNotNull($saved->getCreatedAt(), 'createdAt must be set for lead tracking');
        self::assertNotNull($saved->getTrainingProgram(), 'program association must be set');
    }

    public function testInvalidEmailIsRejectedWithFormError(): void
    {
        $client = $this->client();
        $this->seedProgram();

        $crawler = $client->request('GET', '/training/pmp-certification');
        $form = $crawler->selectButton('Send request')->form([
            'course_inquiry[fullName]' => 'Jane',
            'course_inquiry[companyName]' => 'Acme',
            'course_inquiry[email]' => 'not-an-email',
            'course_inquiry[phone]' => '+254700000000',
            'course_inquiry[inquiryType]' => CourseInquiry::TYPE_QUOTATION,
        ]);

        $client->submit($form);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('body', 'not a valid email');
    }

    /**
     * Regression guard: the program association is NOT NULL at DB level.
     * Before the Assert\NotNull fix this produced a 500 (NotNullConstraintViolation).
     * Simulates a tampered client that omits the program id entirely.
     */
    public function testMissingProgramShowsFormErrorNotDatabaseError(): void
    {
        $client = $this->client();
        $this->seedProgram();

        $crawler = $client->request('GET', '/training/pmp-certification');
        $token = $crawler->filter('input[name="course_inquiry[_token]"]')->attr('value');

        $client->request('POST', '/training/pmp-certification/inquiry', [
            'course_inquiry' => [
                'fullName' => 'Jane',
                'companyName' => 'Acme',
                'email' => 'nullprogram@acme.com',
                'phone' => '+254700000000',
                'inquiryType' => CourseInquiry::TYPE_QUOTATION,
                'preferredDate' => '',
                // trainingProgram deliberately omitted
                '_token' => $token,
            ],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains('body', 'Please choose a training program');

        // And nothing may have been written.
        $em = self::getContainer()->get('doctrine')->getManager();
        self::assertNull($em->getRepository(CourseInquiry::class)->findOneBy(['email' => 'nullprogram@acme.com']));
    }

    public function testRateLimiterBlocksAfterFiveSubmissions(): void
    {
        $client = $this->client();
        $this->seedProgram();

        $em = self::getContainer()->get('doctrine')->getManager();
        $before = count($em->getRepository(CourseInquiry::class)->findAll());

        for ($i = 0; $i < 8; $i++) {
            $crawler = $client->request('GET', '/training/pmp-certification');
            $form = $crawler->selectButton('Send request')->form([
                'course_inquiry[fullName]' => 'Load '.$i,
                'course_inquiry[companyName]' => 'Co',
                'course_inquiry[email]' => "load{$i}@co.com",
                'course_inquiry[phone]' => '+254700000001',
                'course_inquiry[inquiryType]' => CourseInquiry::TYPE_MEETING,
            ]);
            $client->submit($form);
        }

        $em->clear();
        $after = count($em->getRepository(CourseInquiry::class)->findAll());
        $created = $after - $before;

        self::assertLessThan(8, $created, 'Rate limiter did not throttle any submissions');
        self::assertLessThanOrEqual(5, $created, 'More than the configured limit of 5 got through');
    }
}
