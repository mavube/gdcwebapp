<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CourseInquiry;
use App\Entity\Service;
use App\Enum\FunnelTrack;
use App\Enum\IntentTier;
use App\Enum\LeadStatus;
use App\Repository\ServiceRepository;
use App\Repository\TrainingProgramRepository;
use App\Service\InquirySanitizer;
use App\Service\TierValuation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The asynchronous conversion funnel.
 *
 * Both endpoints return JSON only; the deck never triggers a page load, so a
 * validation failure must never redirect or re-render. That is the whole point
 * of the zero-reload requirement.
 *
 * The funnel is hybridised: the payload may name a service, and the service's
 * FunnelTrack then decides which fields are required and whether a corporate
 * email domain is enforced. Payloads without a service keep the legacy
 * three-tier behaviour so existing clients are unaffected.
 */
#[Route("/funnel", name: "app_funnel_")]
final class FunnelController extends AbstractController
{
    public function __construct(
        private readonly InquirySanitizer $sanitizer,
        private readonly TierValuation $valuation,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * Field-level auditing, fired from the client on blur.
     *
     * Validates a single field in isolation and returns the same message the
     * full submission would, so the inline hint never disagrees with the
     * server-side verdict.
     */
    #[Route("/validate", name: "validate", methods: ["POST"])]
    public function validateField(
        Request $request,
        ServiceRepository $services,
        RateLimiterFactory $inquiryLimiter,
    ): JsonResponse {
        $payload = $this->jsonPayload($request);
        if ($payload === null) {
            return $this->json(["ok" => false, "message" => "Malformed request."], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->csrfValid($request, "funnel")) {
            return $this->json([
                "ok" => false,
                "message" => "Your session expired. Please reload the page.",
            ], Response::HTTP_FORBIDDEN);
        }

        // Throttle auditing far more loosely than submission: a real user may
        // tab through the form many times, but a script must not enumerate.
        $limiter = $inquiryLimiter->create("validate-".($request->getClientIp() ?? "unknown"));
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(["ok" => true, "message" => null]);
        }

        $field = $this->str($payload, "field") ?? "";
        $value = $this->str($payload, "value") ?? "";

        // The service decides whether this funnel enforces a corporate domain,
        // so the inline hint agrees with what submission will do.
        $service = $this->resolveService($payload, $services);
        $track = $this->resolveTrack($payload, $service);

        $inquiry = $this->hydrate(new CourseInquiry(), $field, $value);
        $inquiry->setService($service);
        $inquiry->setFunnelTrack($track);

        $violations = $this->validator->validate($inquiry, null, $inquiry->validationGroups());
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === $field) {
                return $this->json([
                    "ok" => false,
                    "field" => $field,
                    "message" => $violation->getMessage(),
                ]);
            }
        }

        return $this->json(["ok" => true, "field" => $field, "message" => null]);
    }

    /**
     * The commit endpoint. Returns JSON so the client can swap in the
     * confirmation panel without a navigation event.
     */
    #[Route("/submit", name: "submit", methods: ["POST"])]
    public function submit(
        Request $request,
        EntityManagerInterface $em,
        TrainingProgramRepository $programs,
        ServiceRepository $services,
        RateLimiterFactory $inquiryLimiter,
    ): JsonResponse {
        $payload = $this->jsonPayload($request);
        if ($payload === null) {
            return $this->json(["ok" => false, "message" => "Malformed request."], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->csrfValid($request, "funnel")) {
            return $this->json([
                "ok" => false,
                "message" => "Your session expired. Please reload the page and try again.",
            ], Response::HTTP_FORBIDDEN);
        }

        $limiter = $inquiryLimiter->create("deck-".($request->getClientIp() ?? "unknown"));
        if (!$limiter->consume()->isAccepted()) {
            return $this->json([
                "ok" => false,
                "message" => "Too many requests. Please try again in a few minutes.",
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $service = $this->resolveService($payload, $services);
        $track = $this->resolveTrack($payload, $service);
        $tier = IntentTier::tryFrom($this->str($payload, "intentTier") ?? "");

        $inquiry = new CourseInquiry();
        $inquiry->setFullName($this->sanitizer->clean($this->str($payload, "fullName"), 100) ?? "");
        $inquiry->setCompanyName($this->sanitizer->clean($this->str($payload, "companyName"), 150));
        $inquiry->setEmail($this->sanitizer->clean($this->str($payload, "email"), 180) ?? "");
        $inquiry->setPhone($this->sanitizer->clean($this->str($payload, "phone"), 30) ?? "");
        $inquiry->setOrganizationScope($this->sanitizer->clean($this->str($payload, "organizationScope"), 150));
        $inquiry->setDepartment($this->sanitizer->clean($this->str($payload, "department"), 120));
        $inquiry->setTeamSize($this->sanitizer->clean($this->str($payload, "teamSize"), 20));
        $inquiry->setPreferredChannel($this->sanitizer->clean($this->str($payload, "preferredChannel"), 60));
        $inquiry->setIntentTier($tier);
        $inquiry->setService($service);
        $inquiry->setFunnelTrack($track);
        $inquiry->setMessage($this->sanitizer->cleanMultiline($this->str($payload, "message"), 2000));
        $inquiry->setStatus(LeadStatus::New);
        $inquiry->setEstimatedValueUsd(
            $service !== null ? $this->valuation->forService($service) : $this->valuation->forTier($tier),
        );

        // The deck does not pick a programme, so default to the first available
        // track when one exists. The column is nullable, so an empty catalogue
        // is not an error.
        if ($programs->count([]) > 0) {
            $inquiry->setTrainingProgram($programs->findOneBy([], ["id" => "ASC"]));
        }

        $violations = $this->validator->validate($inquiry, null, $inquiry->validationGroups());
        if (count($violations) > 0) {
            $errors = [];
            /** @var ConstraintViolationInterface $violation */
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return $this->json([
                "ok" => false,
                "message" => "Please correct the highlighted fields.",
                "errors" => $errors,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Legacy path: with no service resolved, intentTier must be one of the
        // three known tiers. This cannot live on the entity any more, because on
        // a service funnel the same field carries a service slug instead.
        if ($service === null && $tier === null) {
            return $this->json([
                "ok" => false,
                "message" => "Please correct the highlighted fields.",
                "errors" => ["intentTier" => "Please choose how we can help."],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $em->persist($inquiry);
        $em->flush();

        $label = $service?->getTitle() ?? $inquiry->getIntentTier()?->shortLabel() ?? "consultation";

        return $this->json([
            "ok" => true,
            "id" => $inquiry->getId(),
            "tier" => $inquiry->getIntentTier()?->value,
            "tierLabel" => $label,
            "track" => $track->value,
            "service" => $service?->getSlug(),
            "estimatedValueUsd" => $inquiry->getEstimatedValueUsd(),
            // Confirmation panel copy is generated server-side so the wording
            // lives in one place.
            "confirmation" => [
                "heading" => "Briefing request received",
                "body" => sprintf(
                    "Thank you, %s. %s",
                    $inquiry->getFullName(),
                    $track->confirmationWording(),
                ),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Resolves the service named in the payload, if any.
     *
     * The deck's step-1 radio carries the offer slug in intentTier, so that is
     * the primary source; the explicit `service` key is also accepted for
     * clients that send it. An unknown or unpublished slug yields null rather
     * than an error: the lead is still valid, it simply falls back to the legacy
     * three-tier behaviour instead of being lost.
     *
     * @param array<string, mixed> $payload
     */
    private function resolveService(array $payload, ServiceRepository $services): ?Service
    {
        foreach ([$this->str($payload, 'service'), $this->str($payload, 'intentTier')] as $slug) {
            if ($slug === null || $slug === '') {
                continue;
            }

            $service = $services->findOnePublishedBySlug($slug);
            if ($service !== null) {
                return $service;
            }
        }

        return null;
    }

    /**
     * The funnel shape for this submission.
     *
     * The service's own track wins. Without a service the legacy deck is
     * assumed, which is the organisation track, preserving the previous
     * corporate-email requirement exactly.
     *
     * @param array<string, mixed> $payload
     */
    private function resolveTrack(array $payload, ?Service $service): FunnelTrack
    {
        if ($service !== null) {
            return $service->getFunnelTrack();
        }

        return FunnelTrack::tryFrom($this->str($payload, "track") ?? '') ?? FunnelTrack::Organisation;
    }

    /** @return array<string, mixed>|null */
    private function jsonPayload(Request $request): ?array
    {
        $raw = $request->getContent();
        if ($raw === "" || $raw === false) {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Reads a payload key as a string, or null when it is absent or not a
     * scalar. Callers pass the result to setters that require string, so a
     * client sending {"fullName": {...}} yields a validation error rather than
     * a TypeError and a 500.
     *
     * @param array<string, mixed> $payload
     */
    private function str(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        if (is_string($value)) {
            return $value;
        }

        return is_int($value) || is_float($value) ? (string) $value : null;
    }

    /**
     * The deck is JSON-only, so the token travels in the body rather than in a
     * form field. Symfony's CSRF manager validates it against the session.
     */
    private function csrfValid(Request $request, string $tokenId): bool
    {
        $payload = $this->jsonPayload($request);
        $token = $payload["_token"] ?? $request->headers->get("X-CSRF-Token");

        return is_string($token)
            && $this->isCsrfTokenValid($tokenId, $token);
    }

    /** Applies a single field so the field validator can reuse entity rules. */
    private function hydrate(CourseInquiry $inquiry, string $field, string $value): CourseInquiry
    {
        switch ($field) {
            case "fullName":
                $inquiry->setFullName($this->sanitizer->clean($value, 100) ?? "");
                break;
            case "companyName":
                $inquiry->setCompanyName($this->sanitizer->clean($value, 150));
                break;
            case "email":
                $inquiry->setEmail($this->sanitizer->clean($value, 180) ?? "");
                break;
            case "phone":
                $inquiry->setPhone($this->sanitizer->clean($value, 30) ?? "");
                break;
            case "organizationScope":
                $inquiry->setOrganizationScope($this->sanitizer->clean($value, 150));
                break;
            case "department":
                $inquiry->setDepartment($this->sanitizer->clean($value, 120));
                break;
            case "teamSize":
                $inquiry->setTeamSize($this->sanitizer->clean($value, 20));
                break;
            case "preferredChannel":
                $inquiry->setPreferredChannel($this->sanitizer->clean($value, 60));
                break;
            case "intentTier":
                $inquiry->setIntentTier(IntentTier::tryFrom($value));
                break;
            case "message":
                $inquiry->setMessage($this->sanitizer->cleanMultiline($value, 2000));
                break;
        }

        return $inquiry;
    }
}
