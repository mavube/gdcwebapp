<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\CourseInquiry;
use App\Entity\TrainingProgram;
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

    public function testHomePageRenders(): void
    {
        $client = $this->client();
        $this->seedProgram();

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Certification');
    }

    public function testTrainingPageRendersHeroAndBento(): void
    {
        $client = $this->client();
        $this->seedProgram();

        $client->request('GET', '/training/pmp-certification');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('h1');
        self::assertSelectorTextContains('h1', 'PMP Certification');
        // Bento matrix must be present with all four cards.
        self::assertSelectorCount(4, '#solutions article');
        // Inquiry form present.
        self::assertSelectorExists('form[name="course_inquiry"]');
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
