<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\ContentBlock;
use App\Entity\Service;
use App\Enum\FunnelTrack;
use App\Enum\Pillar;
use App\Repository\ContentBlockRepository;
use App\Repository\ServiceRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Phase 5: the backend-managed catalogue and the hybridised funnel.
 *
 * The behaviour under test is the split between the two funnel tracks. GDC sells
 * individual seats on public certification courses and corporate engagements,
 * and the two must not share one form: the corporate rule that keeps personal
 * mailboxes out of an enterprise pipeline is exactly the rule that would reject
 * a professional buying their own PMP seat with a Gmail address.
 */
final class ServiceCatalogueTest extends WebTestCase
{
    private function client(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = self::createClient();
        self::getContainer()->get('cache.rate_limiter')->clear();

        return $client;
    }

    private function services(): ServiceRepository
    {
        return self::getContainer()->get(ServiceRepository::class);
    }

    private function blocks(): ContentBlockRepository
    {
        return self::getContainer()->get(ContentBlockRepository::class);
    }

    /**
     * Ensures a service exists without depending on fixtures:load having run.
     * Tests must not assume a seeded database.
     */
    private function seedService(
        string $slug,
        Pillar $pillar = Pillar::Certification,
        FunnelTrack $track = FunnelTrack::Individual,
        bool $featured = false,
    ): Service {
        $existing = $this->services()->findOneBy(['slug' => $slug]);
        if ($existing !== null) {
            return $existing;
        }

        $em = self::getContainer()->get('doctrine')->getManager();

        $service = new Service();
        $service->setSlug($slug);
        $service->setTitle(ucwords(str_replace('-', ' ', $slug)));
        $service->setTagline('Test tagline for '.$slug.'.');
        $service->setDescription("First paragraph.\n\nSecond paragraph.");
        $service->setPillar($pillar);
        $service->setFunnelTrack($track);
        $service->setCtaLabel('Enquire');
        $service->setDuration('3 days');
        $service->setFeatured($featured);

        $em->persist($service);
        $em->flush();

        return $service;
    }

    private function deckToken(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): string
    {
        $crawler = $client->request('GET', '/');

        return (string) $crawler->filter('#funnel-deck')->attr('data-csrf-token');
    }

    /** @param array<string, mixed> $payload */
    private function postJson(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $url, array $payload): void
    {
        $client->request(
            'POST',
            $url,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $payload['_token'] ?? ''],
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    /** @return array<string, mixed> */
    private function body(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): array
    {
        return json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    // ------------------------------------------------------------- catalogue

    public function testHomePageRendersPillarsFromTheDatabase(): void
    {
        $client = $this->client();
        $this->seedService('pmp-exam-prep-master-class', Pillar::Certification, FunnelTrack::Individual, true);

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        // The pillar heading is the enum label, so this proves the page is driven
        // by the entity rather than by copy in the template.
        self::assertSelectorTextContains('#solutions', Pillar::Certification->label());
    }

    public function testServiceDetailPageRenders(): void
    {
        $client = $this->client();
        $service = $this->seedService('microsoft-project-practical-training', Pillar::ToolMastery, FunnelTrack::Organisation);

        $client->request('GET', '/services/'.$service->getSlug());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $service->getTitle());
        self::assertSelectorTextContains('body', $service->getTagline());
    }

    public function testInactiveServiceIsNotPubliclyReachable(): void
    {
        $client = $this->client();
        $service = $this->seedService('retired-programme');

        $em = self::getContainer()->get('doctrine')->getManager();
        $service->setActive(false);
        $em->flush();

        $client->request('GET', '/services/'.$service->getSlug());
        self::assertResponseStatusCodeSame(404);

        // Restore so a later test in this suite can still see it.
        $service->setActive(true);
        $em->flush();
    }

    public function testUnknownServiceSlugReturns404(): void
    {
        $client = $this->client();
        $client->request('GET', '/services/no-such-programme');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * The XSS guard: a service description is admin-supplied text and must be
     * escaped on the detail page. This mirrors the guard on the training page.
     */
    public function testServiceDescriptionIsEscaped(): void
    {
        $client = $this->client();
        $service = $this->seedService('escape-check-programme');

        $em = self::getContainer()->get('doctrine')->getManager();
        $original = $service->getDescription();
        $service->setDescription('<script>alert("xss")</script>');
        $em->flush();

        $client->request('GET', '/services/'.$service->getSlug());
        $html = (string) $client->getResponse()->getContent();

        self::assertStringNotContainsString('<script>alert', $html, 'Raw script tag leaked into output');
        self::assertStringContainsString('&lt;script&gt;alert', $html, 'Expected escaped script tag');

        $service->setDescription($original);
        $em->flush();
    }

    // --------------------------------------------------------- funnel tracks

    /**
     * The headline behaviour: an individual enrolment accepts a consumer
     * mailbox, because that is what the person buying their own PMP seat has.
     */
    public function testIndividualFunnelAcceptsPersonalEmail(): void
    {
        $client = $this->client();
        $service = $this->seedService('individual-track-programme', Pillar::Certification, FunnelTrack::Individual);
        $token = $this->deckToken($client);

        $this->postJson($client, '/funnel/submit', [
            '_token' => $token,
            'service' => $service->getSlug(),
            'fullName' => 'Amina Hassan',
            'email' => 'amina.hassan@gmail.com',
            'phone' => '+255700000000',
        ]);

        self::assertResponseStatusCodeSame(201);
        $body = $this->body($client);
        self::assertTrue($body['ok']);
        self::assertSame(FunnelTrack::Individual->value, $body['track']);
        self::assertSame($service->getSlug(), $body['service']);
    }

    /**
     * The other half of the same rule: a corporate engagement still rejects a
     * consumer mailbox.
     */
    public function testOrganisationFunnelRejectsPersonalEmail(): void
    {
        $client = $this->client();
        $service = $this->seedService('corporate-track-programme', Pillar::Strategy, FunnelTrack::Organisation);
        $token = $this->deckToken($client);

        $this->postJson($client, '/funnel/submit', [
            '_token' => $token,
            'service' => $service->getSlug(),
            'fullName' => 'Amina Hassan',
            'companyName' => 'Acme Ltd',
            'organizationScope' => '200 staff',
            'email' => 'amina.hassan@gmail.com',
            'phone' => '+255700000000',
        ]);

        self::assertResponseStatusCodeSame(422);
        $body = $this->body($client);
        self::assertFalse($body['ok']);
        self::assertArrayHasKey('email', $body['errors']);
    }

    /** The same offer with a work address is accepted. */
    public function testOrganisationFunnelAcceptsCorporateEmail(): void
    {
        $client = $this->client();
        $service = $this->seedService('corporate-track-accept', Pillar::Strategy, FunnelTrack::Organisation);
        $token = $this->deckToken($client);

        $this->postJson($client, '/funnel/submit', [
            '_token' => $token,
            'service' => $service->getSlug(),
            'fullName' => 'Amina Hassan',
            'companyName' => 'Acme Ltd',
            'organizationScope' => '200 staff',
            'email' => 'amina.hassan@acme.co.tz',
            'phone' => '+255700000000',
        ]);

        self::assertResponseStatusCodeSame(201);
        $body = $this->body($client);
        self::assertSame(FunnelTrack::Organisation->value, $body['track']);
    }

    /**
     * The field audit must agree with submission. A personal address audited on
     * an individual funnel must pass, or the inline hint would contradict the
     * successful submit that follows.
     */
    public function testFieldAuditFollowsTheServiceTrack(): void
    {
        $client = $this->client();
        $service = $this->seedService('audit-track-programme', Pillar::Certification, FunnelTrack::Individual);
        $token = $this->deckToken($client);

        $this->postJson($client, '/funnel/validate', [
            '_token' => $token,
            'service' => $service->getSlug(),
            'field' => 'email',
            'value' => 'amina.hassan@gmail.com',
        ]);

        self::assertResponseIsSuccessful();
        self::assertTrue($this->body($client)['ok'], 'Individual funnel must accept a personal address');
    }

    public function testFieldAuditStillRejectsPersonalEmailOnCorporateTrack(): void
    {
        $client = $this->client();
        $service = $this->seedService('audit-corporate-programme', Pillar::Strategy, FunnelTrack::Organisation);
        $token = $this->deckToken($client);

        $this->postJson($client, '/funnel/validate', [
            '_token' => $token,
            'service' => $service->getSlug(),
            'field' => 'email',
            'value' => 'amina.hassan@gmail.com',
        ]);

        self::assertResponseIsSuccessful();
        self::assertFalse($this->body($client)['ok']);
    }

    /**
     * A lead must be attributable to the offer that produced it. Without this
     * the per-service reporting the catalogue exists to enable is impossible.
     */
    public function testLeadIsAttributedToItsService(): void
    {
        $client = $this->client();
        $service = $this->seedService('attribution-programme', Pillar::ToolMastery, FunnelTrack::Organisation);
        $token = $this->deckToken($client);

        $this->postJson($client, '/funnel/submit', [
            '_token' => $token,
            'service' => $service->getSlug(),
            'fullName' => 'Juma Said',
            'companyName' => 'TANESCO',
            'organizationScope' => '1000+ staff',
            'email' => 'juma.said@tanesco.co.tz',
            'phone' => '+255700000001',
        ]);

        self::assertResponseStatusCodeSame(201);

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->clear();
        $saved = $em->getRepository(\App\Entity\CourseInquiry::class)
            ->findOneBy(['email' => 'juma.said@tanesco.co.tz']);

        self::assertNotNull($saved);
        self::assertNotNull($saved->getService(), 'Lead must record the offer it came from');
        self::assertSame($service->getSlug(), $saved->getService()->getSlug());
        self::assertSame(FunnelTrack::Organisation, $saved->getFunnelTrack());
        self::assertGreaterThan(0, $saved->getEstimatedValueUsd(), 'A service lead must carry a pipeline value');
    }

    // -------------------------------------------------------- content blocks

    public function testContentBlocksAreEditableAndDriveThePage(): void
    {
        $client = $this->client();
        $em = self::getContainer()->get('doctrine')->getManager();

        $block = $this->blocks()->findOneBy(['blockKey' => 'home.hero']);
        if ($block === null) {
            $block = new ContentBlock();
            $block->setBlockKey('home.hero');
            $block->setLabel('Home · Hero');
            $em->persist($block);
            $em->flush();
        }

        $original = $block->getHeading();

        // try/finally, not a plain restore at the end: an assertion failure
        // throws, and without finally it would leave the block edited and break
        // every later test that reads the home page.
        try {
            $block->setHeading('Distinctive editorial heading');
            $em->flush();

            $client->request('GET', '/');
            self::assertSelectorTextContains('h1', 'Distinctive editorial heading');
        } finally {
            $block->setHeading($original ?? 'Certification. Tool mastery. Enterprise strategy.');
            $em->flush();
        }
    }

    /**
     * A block is addressed by key, so deactivating one removes its section
     * rather than leaving stale copy on the page.
     */
    public function testInactiveContentBlockDoesNotRender(): void
    {
        $client = $this->client();
        $em = self::getContainer()->get('doctrine')->getManager();

        $block = $this->blocks()->findOneBy(['blockKey' => 'home.approach']);
        if ($block === null) {
            $block = new ContentBlock();
            $block->setBlockKey('home.approach');
            $block->setLabel('Home · Approach');
            $block->setEyebrow('Our approach');
            $block->setHeading('Aligning People, Processes and Purpose');
            $block->setBody('A test body for the approach section.');
            $em->persist($block);
            $em->flush();
        }

        try {
            // Normalise first. The suite shares one SQLite file and does not
            // roll back, so the row may still be deactivated by an earlier run;
            // asserting on that leftover state would fail for the wrong reason.
            $block->setActive(true);
            $em->flush();

            $client->request('GET', '/');
            self::assertSelectorExists('#approach');

            $block->setActive(false);
            $em->flush();

            $client->request('GET', '/');
            self::assertSelectorNotExists('#approach');
        } finally {
            // Reactivate even if an assertion above failed, so this test cannot
            // poison the next one.
            $block->setActive(true);
            $em->flush();
        }
    }

    // ------------------------------------------------------------- deck config

    public function testDeckCarriesTrackMapAndCopyForSwitching(): void
    {
        $client = $this->client();
        $this->seedService('config-map-programme', Pillar::Certification, FunnelTrack::Individual);

        $crawler = $client->request('GET', '/');
        $json = $crawler->filter('#funnel-config')->text();
        /** @var array<string, mixed> $config */
        $config = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('tracks', $config);
        self::assertSame(FunnelTrack::Individual->value, $config['tracks']['config-map-programme']);
        // Both tracks' copy is present so switching needs no round trip.
        self::assertArrayHasKey(FunnelTrack::Individual->value, $config['copy']);
        self::assertArrayHasKey(FunnelTrack::Organisation->value, $config['copy']);
    }

    public function testServicePageScopesDeckToThatService(): void
    {
        $client = $this->client();
        $service = $this->seedService('scoped-deck-programme', Pillar::ToolMastery, FunnelTrack::Organisation);

        $crawler = $client->request('GET', '/services/'.$service->getSlug());

        self::assertSame($service->getSlug(), $crawler->filter('#funnel-deck')->attr('data-service'));
        self::assertSame(FunnelTrack::Organisation->value, $crawler->filter('#funnel-deck')->attr('data-track'));
    }
}
