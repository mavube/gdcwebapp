<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\ContentBlock;
use App\Entity\ContentItem;
use App\Enum\ContentSection;
use App\Enum\Pillar;
use App\Repository\ContentBlockRepository;
use App\Repository\ContentItemRepository;
use App\Repository\ServiceRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Phase 6: the home page laid out from database content.
 *
 * The point of these tests is the contract between the page and the content it
 * is built from, not the markup. Two properties matter and are asserted
 * repeatedly below:
 *
 * 1. Every section renders from rows, so editing a row changes the page and
 *    removing the rows removes the section. Nothing is baked into a template.
 * 2. A section with no content is absent, not empty: no heading over a blank
 *    area, and no invented string standing in for real content.
 */
final class HomeLayoutTest extends WebTestCase
{
    private function client(): KernelBrowser
    {
        $client = self::createClient();
        self::getContainer()->get('cache.rate_limiter')->clear();

        return $client;
    }

    private function items(): ContentItemRepository
    {
        return self::getContainer()->get(ContentItemRepository::class);
    }

    private function blocks(): ContentBlockRepository
    {
        return self::getContainer()->get(ContentBlockRepository::class);
    }

    /**
     * Ensures a block exists without depending on fixtures:load having run.
     * Tests must not assume a seeded database.
     */
    private function seedBlock(string $key, string $heading): ContentBlock
    {
        $blocks = $this->blocks();
        $block = $blocks->findOneBy(['blockKey' => $key]);

        if ($block === null) {
            $block = new ContentBlock();
            $block->setBlockKey($key);
            $block->setLabel($key);
            $block->setHeading($heading);
            $em = self::getContainer()->get('doctrine')->getManager();
            $em->persist($block);
            $em->flush();
        }

        // The suite shares one SQLite file and does not roll back, so normalise
        // the state this test depends on rather than trusting a previous run.
        $block->setActive(true);
        if ($block->getHeading() === null) {
            $block->setHeading($heading);
        }
        self::getContainer()->get('doctrine')->getManager()->flush();

        return $block;
    }

    private function seedItem(ContentSection $section, array $fields): ContentItem
    {
        $em = self::getContainer()->get('doctrine')->getManager();

        $item = new ContentItem();
        $item->setSection($section);
        $item->setSortOrder(($this->items()->countActiveFor($section) + 1) * 10);

        foreach ($fields as $field => $value) {
            $item->{'set' . ucfirst($field)}($value);
        }

        $em->persist($item);
        $em->flush();

        return $item;
    }

    private function clearItems(ContentSection $section): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->createQuery('DELETE FROM ' . ContentItem::class . ' i WHERE i.section = :section')
            ->setParameter('section', $section)
            ->execute();
    }

    /**
     * Returns the administrator, creating it if the fixtures were not loaded
     * into this database. Tests must not depend on running fixtures:load first.
     */
    private function adminUser(): \App\Entity\AdminUser
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $repo = $em->getRepository(\App\Entity\AdminUser::class);

        $user = $repo->findOneBy(['email' => \App\DataFixtures\AdminUserFixtures::DEFAULT_EMAIL]);
        if ($user !== null) {
            return $user;
        }

        $user = new \App\Entity\AdminUser();
        $user->setEmail(\App\DataFixtures\AdminUserFixtures::DEFAULT_EMAIL);
        $user->setDisplayName('GDC Command Centre');
        $user->setRoles([\App\Entity\AdminUser::ROLE_GDC_ADMIN]);
        $user->setPassword(
            self::getContainer()->get(\Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface::class)
                ->hashPassword($user, \App\DataFixtures\AdminUserFixtures::DEFAULT_PASSWORD),
        );

        $em->persist($user);
        $em->flush();

        return $user;
    }

    // ------------------------------------------------------- the composition

    public function testHomeRendersEverySectionInOrder(): void
    {
        $client = $this->client();
        $this->seedBlock('home.hero', 'Hero heading');
        $this->seedBlock('home.work', 'Work heading');
        $this->seedBlock('home.services', 'Offer heading');
        $this->seedBlock('home.pillars', 'Catalogue heading');
        $this->seedBlock('home.method', 'Method heading');
        $this->seedBlock('home.faq', 'Questions heading');
        $this->seedBlock('home.contact', 'Closing heading');

        // Rows behind the two sections that render only from rows.
        $this->seedItem(ContentSection::HomeFaq, [
            'title' => 'Is this the questions section?',
            'body' => 'It is.',
        ]);
        $this->seedItem(ContentSection::SiteContact, [
            'title' => 'Email',
            'subtitle' => 'home-layout@example.test',
        ]);

        $crawler = $client->request('GET', '/');
        self::assertResponseIsSuccessful();

        // The order is the design's, and a visitor's path down the page.
        $ids = $crawler->filter('section[id], footer')->each(
            static fn ($node) => $node->attr('id') ?? 'footer',
        );

        $expected = ['hero', 'work', 'services', 'solutions', 'method', 'faq', 'contact'];
        $present = array_values(array_intersect($ids, $expected));

        self::assertSame($expected, $present, 'The home sections must appear in the design order.');
    }

    /**
     * A block with no heading but with a body still renders its body: an editor
     * is not forced to write a heading the section does not need.
     */
    public function testSectionRendersBodyWithoutAHeading(): void
    {
        $client = $this->client();
        $block = $this->seedBlock('home.contact', 'Temporary heading');
        $block->setHeading(null);
        $block->setBody('A closing line with no heading above it.');
        self::getContainer()->get('doctrine')->getManager()->flush();

        $crawler = $client->request('GET', '/');

        self::assertStringContainsString(
            'A closing line with no heading above it.',
            $crawler->filter('#contact')->text(),
        );

        $block->setHeading('Closing heading');
        self::getContainer()->get('doctrine')->getManager()->flush();
    }

    // ------------------------------------------------- rows drive the sections

    public function testFaqRendersOneEntryPerRow(): void
    {
        $client = $this->client();
        $this->seedBlock('home.faq', 'Questions heading');
        $this->clearItems(ContentSection::HomeFaq);

        $this->seedItem(ContentSection::HomeFaq, [
            'title' => 'First seeded question?',
            'body' => 'First seeded answer.',
        ]);
        $this->seedItem(ContentSection::HomeFaq, [
            'title' => 'Second seeded question?',
            'body' => 'Second seeded answer.',
        ]);

        $crawler = $client->request('GET', '/');
        $faq = $crawler->filter('#faq');

        self::assertSame(2, $faq->filter('details')->count(), 'One disclosure per FAQ row.');
        self::assertStringContainsString('First seeded question?', $faq->text());
        self::assertStringContainsString('Second seeded answer.', $faq->text());
    }

    public function testFaqSectionIsAbsentWhenItHasNoRows(): void
    {
        $client = $this->client();
        $this->seedBlock('home.faq', 'Questions heading');
        $this->clearItems(ContentSection::HomeFaq);

        $crawler = $client->request('GET', '/');

        // The heading alone must not keep the section on the page: a heading
        // over nothing is the failure this guards against.
        self::assertSame(0, $crawler->filter('#faq')->count());
    }

    public function testNumberedOfferRendersOneRowPerItem(): void
    {
        $client = $this->client();
        $this->seedBlock('home.services', 'Offer heading');
        $this->clearItems(ContentSection::HomeServices);

        $this->seedItem(ContentSection::HomeServices, [
            'title' => 'First seeded offer',
            'body' => 'First seeded offer body.',
        ]);
        $this->seedItem(ContentSection::HomeServices, [
            'title' => 'Second seeded offer',
            'body' => 'Second seeded offer body.',
        ]);

        $crawler = $client->request('GET', '/');
        $services = $crawler->filter('#services');

        self::assertStringContainsString('First seeded offer', $services->text());
        self::assertStringContainsString('Second seeded offer', $services->text());
        self::assertStringContainsString('01.', $services->text());
        self::assertStringContainsString('02.', $services->text());
    }

    /**
     * The closing band's contact details come from rows, so an editor changes a
     * telephone number in one place and both the band and the footer follow.
     */
    public function testContactRowsFeedTheClosingBand(): void
    {
        $client = $this->client();
        $this->seedBlock('home.contact', 'Closing heading');
        $this->clearItems(ContentSection::SiteContact);
        $this->seedItem(ContentSection::SiteContact, [
            'title' => 'Call',
            'subtitle' => '+255 000 000 000',
            'linkTarget' => 'tel:+255000000000',
        ]);

        $crawler = $client->request('GET', '/');
        $contact = $crawler->filter('#contact');

        self::assertStringContainsString('+255 000 000 000', $contact->text());
        self::assertSame(1, $contact->filter('a[href="tel:+255000000000"]')->count());
    }

    /**
     * A link target of "route:name" resolves through the router; a service slug
     * resolves to that programme's page. Both are how the fixtures address the
     * pages that exist, so neither may silently degrade to "#".
     */
    public function testLinkTargetsResolveToRealDestinations(): void
    {
        $client = $this->client();
        $this->seedBlock('home.services', 'Offer heading');
        $this->clearItems(ContentSection::HomeServices);

        $services = self::getContainer()->get(ServiceRepository::class);
        $service = $services->findOneBy(['slug' => 'home-layout-link-probe']);
        if ($service === null) {
            $service = (new \App\Entity\Service())
                ->setSlug('home-layout-link-probe')
                ->setTitle('Home Layout Link Probe')
                ->setTagline('A seeded offer for the link test.')
                ->setDescription('A seeded offer used to prove link targets resolve to a real programme page.')
                ->setPillar(Pillar::Certification);
            $em = self::getContainer()->get('doctrine')->getManager();
            $em->persist($service);
            $em->flush();
        }

        $this->seedItem(ContentSection::HomeServices, [
            'title' => 'Linked offer',
            'linkTarget' => 'home-layout-link-probe',
        ]);

        $crawler = $client->request('GET', '/');
        $links = $crawler->filter('#services a')->each(static fn ($node) => $node->attr('href'));

        self::assertContains('/services/home-layout-link-probe', $links);
        self::assertNotContains('#', $links, 'A resolvable target must not fall back to a dead anchor.');
    }

    /**
     * The header and the footer are rendered by base.html.twig, which no
     * controller prepares data for. This proves the shell reads its own rows.
     */
    public function testHeaderNavigationRendersFromRows(): void
    {
        $client = $this->client();
        $this->clearItems(ContentSection::SiteNav);
        $this->seedItem(ContentSection::SiteNav, [
            'title' => 'Shell nav probe',
            'linkTarget' => 'route:app_service_index',
        ]);

        $crawler = $client->request('GET', '/');
        $nav = $crawler->filter('nav[aria-label="Primary"]');

        self::assertStringContainsString('Shell nav probe', $nav->text());
        self::assertSame(1, $nav->filter('a[href="/services"]')->count());
    }

    /**
     * A row can be taken offline without being deleted, and taking it offline
     * removes it from the page.
     */
    public function testInactiveRowIsNotRendered(): void
    {
        $client = $this->client();
        $this->seedBlock('home.faq', 'Questions heading');
        $this->clearItems(ContentSection::HomeFaq);

        $item = $this->seedItem(ContentSection::HomeFaq, [
            'title' => 'A question that will be hidden?',
            'body' => 'Hidden answer.',
        ]);

        $item->setActive(false);
        self::getContainer()->get('doctrine')->getManager()->flush();

        $crawler = $client->request('GET', '/');

        self::assertStringNotContainsString('A question that will be hidden?', $crawler->text());
        self::assertSame(0, $crawler->filter('#faq')->count(), 'No visible rows means no section.');

        $item->setActive(true);
        self::getContainer()->get('doctrine')->getManager()->flush();
    }

    // ------------------------------------------------------------ no fake copy

    /**
     * The reviews section renders only from real review rows. It ships with
     * none, so the section is absent and no quotation appears. This is the test
     * that would fail if someone seeded invented testimonials.
     */
    public function testReviewsSectionIsAbsentWithoutReviewRows(): void
    {
        $client = $this->client();
        $this->seedBlock('home.reviews', 'What clients say');
        $this->clearItems(ContentSection::HomeReviews);

        $client->request('GET', '/');

        self::assertSame(0, $client->getCrawler()->filter('#reviews')->count());
    }

    // ------------------------------------------------------------- the editor

    /**
     * The whole point of the phase: an editor changes a row and the page
     * follows. This drives the real form through the real controller.
     */
    public function testEditingARowThroughTheAdminChangesThePage(): void
    {
        $client = $this->client();
        $client->loginUser($this->adminUser());

        $this->seedBlock('home.faq', 'Questions heading');
        $this->clearItems(ContentSection::HomeFaq);
        $item = $this->seedItem(ContentSection::HomeFaq, [
            'title' => 'Before the edit?',
            'body' => 'Before the edit body.',
        ]);

        // The index lists both entities, so it is the surface an editor lands on.
        $client->request('GET', '/admin/content');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('home.faq', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('Before the edit?', (string) $client->getResponse()->getContent());

        $crawler = $client->request('GET', '/admin/content/items/' . $item->getId() . '/edit');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save changes')->form([
            'content_item[title]' => 'After the edit?',
            'content_item[body]' => 'After the edit body.',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/admin/content#home.faq');

        $client->request('GET', '/');
        $html = $client->getResponse()->getContent();

        self::assertStringContainsString('After the edit?', $html);
        self::assertStringNotContainsString('Before the edit?', $html);
    }

    /**
     * A new row added through the admin appears on the page without any code
     * change, which is the property the phase exists to deliver.
     */
    public function testAddingARowThroughTheAdminPublishesIt(): void
    {
        $client = $this->client();
        $client->loginUser($this->adminUser());

        $this->seedBlock('home.faq', 'Questions heading');

        $crawler = $client->request('GET', '/admin/content/sections/home.faq/items/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Add row')->form([
            'content_item[title]' => 'A question added through the admin?',
            'content_item[body]' => 'An answer added through the admin.',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/admin/content#home.faq');

        $client->request('GET', '/');

        self::assertStringContainsString('A question added through the admin?', $client->getResponse()->getContent());
    }

    public function testContentEditorRequiresTheAdminRole(): void
    {
        $client = $this->client();
        $client->request('GET', '/admin/content');

        // Redirected to the login page rather than shown the editor.
        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    // ----------------------------------------------------------- link resolver

    /**
     * The resolver decides what a stored target means. Each case is a form an
     * editor will actually type, and the contact ones previously resolved to a
     * dead "/services/tel:..." path.
     *
     */
    #[DataProvider('linkTargets')]
    public function testLinkTargetsResolveAsDocumented(string $target, ?string $expected): void
    {
        $client = $this->client();
        $link = self::getContainer()->get(\App\Twig\BrandExtension::class)->link($target);

        self::assertSame($expected, $link);
    }

    /** @return iterable<string, array{string, string|null}> */
    public static function linkTargets(): iterable
    {
        yield 'empty is omitted' => ['', null];
        yield 'whitespace is omitted' => ['   ', null];
        yield 'anchor is kept' => ['#faq', '#faq'];
        yield 'path is kept' => ['/services', '/services'];
        yield 'external url is kept' => ['https://example.com/x', 'https://example.com/x'];
        yield 'telephone is kept' => ['tel:+255753000360', 'tel:+255753000360'];
        yield 'email is kept' => ['mailto:info@gdc.co.tz', 'mailto:info@gdc.co.tz'];
        yield 'named route is generated' => ['route:app_service_index', '/services'];
        yield 'bare value is a service slug' => ['pmp-exam-prep', '/services/pmp-exam-prep'];
    }

    public function testReviewsRenderFromRowsWhenTheyExist(): void
    {
        $client = $this->client();
        $this->seedBlock('home.reviews', 'What clients say');
        $this->clearItems(ContentSection::HomeReviews);

        $this->seedItem(ContentSection::HomeReviews, [
            'title' => 'A Real Reviewer',
            'subtitle' => 'Programme Lead, Seeded Organisation',
            'body' => 'A review body supplied through the admin.',
        ]);

        $crawler = $client->request('GET', '/');
        $reviews = $crawler->filter('#reviews');

        self::assertSame(1, $reviews->count());
        self::assertStringContainsString('A review body supplied through the admin.', $reviews->text());
        self::assertStringContainsString('A Real Reviewer', $reviews->text());
    }

    /**
     * The portrait stack is a media-led list. With no rows the panel must fall
     * back to its own branded mark rather than rendering an empty grid or a
     * broken image reference.
     */
    public function testMethodPanelRendersWithoutPortraits(): void
    {
        $client = $this->client();
        $this->seedBlock('home.method', 'Method heading');
        $this->clearItems(ContentSection::HomeMethodAvatars);

        $crawler = $client->request('GET', '/');

        self::assertSame(1, $crawler->filter('#method')->count());
        self::assertSame(
            0,
            $crawler->filter('#method img')->count(),
            'No portrait rows means no image tags, not broken ones.',
        );
    }
}
