<?php

declare(strict_types=1);

namespace App\Tests;

use App\DataFixtures\AdminUserFixtures;
use App\Entity\AdminUser;
use App\Entity\CourseInquiry;
use App\Enum\IntentTier;
use App\Enum\LeadStatus;
use App\Service\InquirySanitizer;
use App\Service\TierValuation;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Phase 3: the conversion funnel, the administrative telemetry dashboard and the
 * boundaries that separate them.
 */
final class FunnelAndTelemetryTest extends WebTestCase
{
    private const ADMIN_EMAIL = "admin@gdcconsulting.com";
    private const ADMIN_PASSWORD = "ChangeMe!2026";

    /**
     * Boots the kernel and clears the rate limiter. The limiter counter lives in
     * a cache pool that survives between tests, so without this the sixth
     * submission in the whole suite would be rejected.
     */
    private function client(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = self::createClient();
        self::getContainer()->get("cache.rate_limiter")->clear();

        return $client;
    }

    /**
     * Removes every inquiry so assertions about "the saved lead" cannot match a
     * row left behind by an earlier test run. The admin account is seeded on
     * demand instead of being deleted, since it is shared.
     */
    private function resetInquiries(): void
    {
        $em = self::getContainer()->get("doctrine")->getManager();
        $em->createQuery("DELETE FROM " . CourseInquiry::class . " i")->execute();
    }

    /**
     * Returns the administrator, creating it if the fixtures were not loaded
     * into this database. Tests must not depend on running fixtures:load first.
     */
    private function adminUser(): AdminUser
    {
        $em = self::getContainer()->get("doctrine")->getManager();
        $repo = $em->getRepository(AdminUser::class);

        $user = $repo->findOneBy(["email" => AdminUserFixtures::DEFAULT_EMAIL]);
        if ($user !== null) {
            return $user;
        }

        $user = new AdminUser();
        $user->setEmail(AdminUserFixtures::DEFAULT_EMAIL);
        $user->setDisplayName("GDC Command Centre");
        $user->setRoles([AdminUser::ROLE_GDC_ADMIN]);
        $user->setPassword(
            self::getContainer()->get(UserPasswordHasherInterface::class)
                ->hashPassword($user, AdminUserFixtures::DEFAULT_PASSWORD),
        );

        $em->persist($user);
        $em->flush();

        return $user;
    }

    /** Opens the deck's page and returns the CSRF token the JS would read. */
    private function deckToken(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): string
    {
        $crawler = $client->request("GET", "/");

        return (string) $crawler->filter("#funnel-deck")->attr("data-csrf-token");
    }

    /** @param array<string, mixed> $payload */
    private function postJson(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $url, array $payload): void
    {
        $client->request(
            "POST",
            $url,
            [],
            [],
            ["CONTENT_TYPE" => "application/json", "HTTP_X_CSRF_TOKEN" => $payload["_token"] ?? ""],
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    // ---------------------------------------------------------------- protocol 1

    public function testDeckIsPresentOnEveryPublicPage(): void
    {
        $client = $this->client();

        $client->request("GET", "/");
        self::assertSelectorExists("#funnel-deck");
        self::assertSelectorExists("#funnel-overlay");
        self::assertSelectorExists("[data-funnel-open]");
    }

    public function testDeckCarriesCsrfToken(): void
    {
        $client = $this->client();

        self::assertNotSame("", $this->deckToken($client));
    }

    /**
     * The zero-reload requirement: submission answers with JSON, never a
     * redirect or an HTML document.
     */
    public function testSubmissionReturnsJsonNotARedirect(): void
    {
        $client = $this->client();
        $token = $this->deckToken($client);

        $this->postJson($client, "/funnel/submit", [
            "_token" => $token,
            "intentTier" => IntentTier::ValueBlueprint->value,
            "fullName" => "Jane Okafor",
            "companyName" => "Acme Industries Ltd",
            "email" => "jane.okafor@acme.com",
            "organizationScope" => "500-1000 staff, 3 regions",
            "phone" => "+254700000000",
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame("content-type", "application/json");

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($body["ok"]);
        self::assertSame(48_000, $body["estimatedValueUsd"]);
        self::assertArrayHasKey("confirmation", $body);
        self::assertStringContainsString("Jane Okafor", $body["confirmation"]["body"]);
    }

    public function testValidationFailureReturns422JsonNotHtml(): void
    {
        $client = $this->client();
        $token = $this->deckToken($client);

        $this->postJson($client, "/funnel/submit", [
            "_token" => $token,
            "intentTier" => IntentTier::Mentorship->value,
            "fullName" => "",
            "companyName" => "Acme",
            "email" => "not-an-email",
            "organizationScope" => "50 staff",
            "phone" => "+254700000000",
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame("content-type", "application/json");

        /** @var array{errors: array<string, string>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey("fullName", $body["errors"]);
        self::assertArrayHasKey("email", $body["errors"]);
    }

    public function testFieldAuditAgreesWithFullSubmission(): void
    {
        $client = $this->client();
        $token = $this->deckToken($client);

        $this->postJson($client, "/funnel/validate", [
            "_token" => $token,
            "field" => "email",
            "value" => "someone@gmail.com",
        ]);

        self::assertResponseIsSuccessful();
        /** @var array{ok: bool, message: ?string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($body["ok"]);
        self::assertSame("Please provide an authorized enterprise email node.", $body["message"]);
    }

    public function testMissingCsrfTokenIsRejected(): void
    {
        $client = $this->client();
        $this->deckToken($client);

        $this->postJson($client, "/funnel/submit", [
            "intentTier" => IntentTier::Mentorship->value,
            "fullName" => "Mallory",
            "companyName" => "X",
            "email" => "m@x.com",
            "phone" => "+254700000003",
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testForgedCsrfTokenIsRejected(): void
    {
        $client = $this->client();
        $this->deckToken($client);

        $this->postJson($client, "/funnel/submit", [
            "_token" => "forged-token-value",
            "intentTier" => IntentTier::Mentorship->value,
            "fullName" => "Mallory",
            "companyName" => "X",
            "email" => "m@x.com",
            "phone" => "+254700000003",
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testMalformedJsonIsRejectedWith400(): void
    {
        $client = $this->client();
        $token = $this->deckToken($client);

        $client->request(
            "POST",
            "/funnel/submit",
            [],
            [],
            ["CONTENT_TYPE" => "application/json", "HTTP_X_CSRF_TOKEN" => $token],
            "{not json",
        );

        self::assertResponseStatusCodeSame(400);
    }

    /**
     * A client sending {"fullName": {...}} must get a validation error, not a
     * TypeError from a string-typed setter.
     */
    public function testNonStringPayloadValuesDoNotCauseServerError(): void
    {
        $client = $this->client();
        $token = $this->deckToken($client);

        $this->postJson($client, "/funnel/submit", [
            "_token" => $token,
            "intentTier" => IntentTier::Mentorship->value,
            "fullName" => ["nested" => "object"],
            "companyName" => ["nested"],
            "email" => "z@z.com",
            "phone" => "+254700000004",
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    // ---------------------------------------------------------------- protocol 2

    /**
     * @return iterable<string, array{string}>
     */
    public static function consumerDomainProvider(): iterable
    {
        yield "gmail" => ["jane@gmail.com"];
        yield "yahoo" => ["jane@yahoo.com"];
        yield "outlook" => ["jane@outlook.com"];
        yield "icloud" => ["jane@icloud.com"];
        yield "proton" => ["jane@proton.me"];
        yield "uppercase" => ["JANE@GMAIL.COM"];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("consumerDomainProvider")]
    public function testConsumerMailboxesAreRejected(string $email): void
    {
        $client = $this->client();
        $token = $this->deckToken($client);

        $this->postJson($client, "/funnel/submit", [
            "_token" => $token,
            "intentTier" => IntentTier::Mentorship->value,
            "fullName" => "Jane",
            "companyName" => "Acme",
            "email" => $email,
            "organizationScope" => "50 staff",
            "phone" => "+254700000000",
        ]);

        self::assertResponseStatusCodeSame(422);
        /** @var array{errors: array<string, string>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey("email", $body["errors"]);
        self::assertSame("Please provide an authorized enterprise email node.", $body["errors"]["email"]);
    }

    public function testCorporateMailboxIsAccepted(): void
    {
        $client = $this->client();
        $token = $this->deckToken($client);

        $this->postJson($client, "/funnel/submit", [
            "_token" => $token,
            "intentTier" => IntentTier::Credentialing->value,
            "fullName" => "Jane Okafor",
            "companyName" => "Acme Industries Ltd",
            "email" => "jane.okafor@acme-industries.co.ke",
            "organizationScope" => "500-1000 staff",
            "phone" => "+254700000000",
        ]);

        self::assertResponseStatusCodeSame(201);
    }

    /**
     * A subdomain of a blocked provider must not be treated as the blocked
     * domain itself, and must not slip through either way by accident.
     */
    public function testLookalikeDomainIsNotBlockedAsConsumerMailbox(): void
    {
        $client = $this->client();
        $token = $this->deckToken($client);

        $this->postJson($client, "/funnel/validate", [
            "_token" => $token,
            "field" => "email",
            "value" => "jane@notgmail.com",
        ]);

        /** @var array{ok: bool} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($body["ok"], "notgmail.com is not gmail.com and must be allowed");
    }

    // ---------------------------------------------------------------- protocol 3

    public function testTimestampIsStoredWithoutTruncation(): void
    {
        $client = $this->client();
        $this->resetInquiries();
        $token = $this->deckToken($client);

        $before = new \DateTimeImmutable("now", new \DateTimeZone("UTC"));

        $this->postJson($client, "/funnel/submit", [
            "_token" => $token,
            "intentTier" => IntentTier::Mentorship->value,
            "fullName" => "Time Tester",
            "companyName" => "Chronos Ltd",
            "email" => "time@chronos.com",
            "organizationScope" => "10 staff",
            "phone" => "+254700000005",
        ]);
        self::assertResponseStatusCodeSame(201);

        $after = new \DateTimeImmutable("now", new \DateTimeZone("UTC"));

        $saved = self::getContainer()->get("doctrine")->getManager()
            ->getRepository(CourseInquiry::class)
            ->findOneBy(["email" => "time@chronos.com"]);

        self::assertNotNull($saved);
        $created = $saved->getCreatedAt();
        self::assertNotNull($created);
        self::assertGreaterThanOrEqual($before->getTimestamp(), $created->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $created->getTimestamp());
    }

    /**
     * The stored value must survive a round trip unchanged. Reading back a
     * timestamp and getting a different value is the classic timezone bug.
     */
    public function testTimestampRoundTripsUnchanged(): void
    {
        $client = $this->client();
        $this->resetInquiries();
        $token = $this->deckToken($client);

        $this->postJson($client, "/funnel/submit", [
            "_token" => $token,
            "intentTier" => IntentTier::Mentorship->value,
            "fullName" => "Round Trip",
            "companyName" => "Trip Ltd",
            "email" => "round@trip.com",
            "organizationScope" => "10 staff",
            "phone" => "+254700000006",
        ]);

        $em = self::getContainer()->get("doctrine")->getManager();
        $saved = $em->getRepository(CourseInquiry::class)->findOneBy(["email" => "round@trip.com"]);
        self::assertNotNull($saved);

        $original = $saved->getCreatedAt()?->format("Y-m-d H:i:s");
        $em->clear();

        $reloaded = $em->getRepository(CourseInquiry::class)->findOneBy(["email" => "round@trip.com"]);
        self::assertNotNull($reloaded);
        self::assertSame($original, $reloaded->getCreatedAt()?->format("Y-m-d H:i:s"));
    }

    // ---------------------------------------------------------------- sanitizing

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function xssPayloadProvider(): iterable
    {
        yield "script tag" => ["<script>alert(1)</script>Eve", "Eve"];
        yield "img onerror" => ["<img src=x onerror=alert(1)>Gamma", "Gamma"];
        // The escaped form survives the sanitizer and the first strip, then
        // decodes to a live tag; the second strip removes it, leaving the text.
        yield "escaped script" => ["&lt;script&gt;alert(1)&lt;/script&gt;Eve", "alert(1)Eve"];
        // The sanitizer drops the whole svg subtree rather than its tags.
        yield "svg onload" => ["<svg onload=alert(1)>x", ""];
        yield "bold markup" => ["<b>Acme</b> Ltd", "Acme Ltd"];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("xssPayloadProvider")]
    public function testSanitizerProducesTagFreePlainText(string $input, string $expected): void
    {
        $sanitizer = self::getContainer()->get(InquirySanitizer::class);

        $plain = $sanitizer->toPlainText($input);

        self::assertSame($expected, $plain);
        self::assertStringNotContainsString("<", $plain, "Tag delimiter leaked into plain text");
    }

    public function testStoredValuesAreTagFree(): void
    {
        $client = $this->client();
        $this->resetInquiries();
        $token = $this->deckToken($client);

        $this->postJson($client, "/funnel/submit", [
            "_token" => $token,
            "intentTier" => IntentTier::Credentialing->value,
            "fullName" => "<script>alert(1)</script>Eve",
            "companyName" => "<img src=x onerror=alert(1)>Gamma Corp",
            "email" => "eve@gamma.com",
            "organizationScope" => "1 region",
            "phone" => "+254700000007",
        ]);
        self::assertResponseStatusCodeSame(201);

        $saved = self::getContainer()->get("doctrine")->getManager()
            ->getRepository(CourseInquiry::class)
            ->findOneBy(["email" => "eve@gamma.com"]);

        self::assertNotNull($saved);
        self::assertStringNotContainsString("<", (string) $saved->getFullName());
        self::assertStringNotContainsString("<", (string) $saved->getCompanyName());
    }

    public function testAmpersandSurvivesSanitizing(): void
    {
        $sanitizer = self::getContainer()->get(InquirySanitizer::class);

        self::assertSame("Acme & Sons", $sanitizer->clean("Acme &amp; Sons", 150));
    }

    public function testOverlongInputIsTruncatedNotRejected(): void
    {
        $sanitizer = self::getContainer()->get(InquirySanitizer::class);

        self::assertSame(150, mb_strlen((string) $sanitizer->clean(str_repeat("a", 500), 150)));
    }

    // ---------------------------------------------------------------- domain rules

    public function testValuationMatchesTierDefaults(): void
    {
        $valuation = self::getContainer()->get(TierValuation::class);

        self::assertSame(48_000, $valuation->forTier(IntentTier::ValueBlueprint));
        self::assertSame(18_500, $valuation->forTier(IntentTier::Credentialing));
        self::assertSame(12_000, $valuation->forTier(IntentTier::Mentorship));
        self::assertSame(0, $valuation->forTier(null));
    }

    public function testSubmittedLeadStartsInNewStatus(): void
    {
        $client = $this->client();
        $this->resetInquiries();
        $token = $this->deckToken($client);

        $this->postJson($client, "/funnel/submit", [
            "_token" => $token,
            "intentTier" => IntentTier::Mentorship->value,
            "fullName" => "Status Check",
            "companyName" => "Check Ltd",
            "email" => "status@check.com",
            "organizationScope" => "10 staff",
            "phone" => "+254700000008",
        ]);

        $saved = self::getContainer()->get("doctrine")->getManager()
            ->getRepository(CourseInquiry::class)
            ->findOneBy(["email" => "status@check.com"]);

        self::assertNotNull($saved);
        self::assertSame(LeadStatus::New, $saved->getStatus());
    }

    public function testUnknownTierIsRejected(): void
    {
        $client = $this->client();
        $token = $this->deckToken($client);

        $this->postJson($client, "/funnel/submit", [
            "_token" => $token,
            "intentTier" => "not-a-real-tier",
            "fullName" => "Bad Tier",
            "companyName" => "Bad Ltd",
            "email" => "bad@tier.com",
            "organizationScope" => "10 staff",
            "phone" => "+254700000009",
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    // ---------------------------------------------------------------- admin boundary

    public function testAnonymousTelemetryRequestRedirectsToLogin(): void
    {
        $client = $this->client();

        $client->request("GET", "/admin/telemetry");

        self::assertResponseRedirects("http://localhost/login");
    }

    public function testLoginPageRenders(): void
    {
        $client = $this->client();

        $client->request("GET", "/login");

        self::assertResponseIsSuccessful();
        self::assertSelectorExists("input[name=_username]");
        self::assertSelectorExists("input[name=_password]");
    }

    public function testAdminCanReachTelemetryDashboard(): void
    {
        $client = $this->client();
        $this->loginAsAdmin($client);

        $client->request("GET", "/admin/telemetry");

        self::assertResponseIsSuccessful();
        self::assertSelectorExists("table");
    }

    public function testWrongPasswordDoesNotGrantAccess(): void
    {
        $client = $this->client();

        $crawler = $client->request("GET", "/login");
        $form = $crawler->selectButton("Sign in")->form([
            "_username" => self::ADMIN_EMAIL,
            "_password" => "definitely-the-wrong-password",
        ]);
        $client->submit($form);

        self::assertResponseRedirects("http://localhost/login");

        $client->request("GET", "/admin/telemetry");
        self::assertResponseRedirects("http://localhost/login");
    }

    /**
     * The dashboard must escape stored lead data rather than render it.
     */
    public function testDashboardEscapesStoredLeadData(): void
    {
        $client = $this->client();
        $token = $this->deckToken($client);

        $this->postJson($client, "/funnel/submit", [
            "_token" => $token,
            "intentTier" => IntentTier::Mentorship->value,
            "fullName" => "Escaping Probe",
            "companyName" => "Probe & Co",
            "email" => "probe@escaping.com",
            "organizationScope" => "1 site",
            "phone" => "+254700000010",
        ]);

        $this->loginAsAdmin($client);
        $client->request("GET", "/admin/telemetry");
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString("Probe &amp; Co", $html, "Ampersand must be entity-encoded");
        self::assertStringNotContainsString("<script", $html);
    }

    public function testLogoutEndsTheAdminSession(): void
    {
        $client = $this->client();
        $this->loginAsAdmin($client);

        $client->request("GET", "/admin/telemetry");
        self::assertResponseIsSuccessful();

        $client->request("GET", "/logout");
        $client->request("GET", "/admin/telemetry");
        self::assertResponseRedirects("http://localhost/login");
    }

    private function loginAsAdmin(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): void
    {
        $client->loginUser($this->adminUser());
    }
}
