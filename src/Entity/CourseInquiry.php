<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\FunnelTrack;
use App\Enum\IntentTier;
use App\Enum\LeadStatus;
use App\Repository\CourseInquiryRepository;
use App\Validator\Constraints as GdcAssert;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CourseInquiryRepository::class)]
#[ORM\Table(name: "gdc_course_inquiries")]
#[ORM\HasLifecycleCallbacks]
class CourseInquiry
{
    public const TYPE_QUOTATION = "Quotation";
    public const TYPE_MEETING = "Meeting";

    /**
     * Validation group for the legacy training-track form, where a specific
     * programme must be chosen.
     */
    public const GROUP_TRACK = 'track';

    /**
     * Validation group for the conversion deck. The deck submits without a
     * programme because a prospect may not yet know which one they need, but it
     * does collect the organization's breadth, which the track form does not.
     */
    public const GROUP_DECK = 'deck';

    /**
     * Validation group that enforces the corporate-domain rule. Applied only
     * when the funnel track is the organisation one; individual enrolment
     * validates without it so a personal mailbox is accepted.
     */
    public const GROUP_CORPORATE = 'corporate';

    /**
     * Validation group for the questions only a corporate buyer is asked, such
     * as company name and organisation scope. Individual enrolment validates
     * without it, so a professional buying their own seat is not asked to name
     * an employer they are not buying on behalf of.
     */
    public const GROUP_ORGANISATION = 'organisation';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Please tell us your full name.'), Assert\Length(max: 100)]
    private ?string $fullName = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    #[Assert\NotBlank(groups: [self::GROUP_TRACK, self::GROUP_ORGANISATION], message: 'Please tell us your organization.')]
    private ?string $companyName = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Please provide an authorized enterprise email node.')]
    #[Assert\Email(message: 'That is not a valid email address.')]
    #[Assert\Length(max: 180)]
    #[GdcAssert\CorporateEmail]
    private ?string $email = null;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank, Assert\Length(max: 30)]
    #[Assert\Regex(pattern: '/^[0-9 +().-]{6,30}$/', message: 'Please enter a valid phone number.')]
    private ?string $phone = null;

    /** Describes the breadth of the organization, e.g. "500-1000 staff, 3 regions". */
    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    #[Assert\NotBlank(groups: [self::GROUP_ORGANISATION], message: "Please describe your organization's scope.")]
    private ?string $organizationScope = null;

    /** The prospect's department or job title, asked on the corporate track. */
    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $department = null;

    /** Team-size band, e.g. "5-15". Asked on the corporate track. */
    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    private ?string $teamSize = null;

    /** Preferred meeting channel, e.g. "Zoom", "Microsoft Teams", "On site". */
    #[ORM\Column(length: 60, nullable: true)]
    #[Assert\Length(max: 60)]
    private ?string $preferredChannel = null;

    /**
     * The corporate deliverable selected in step 1 of the conversion deck.
     *
     * Retained for the legacy three-tier deck. New funnels record the concrete
     * Service instead, which is what makes per-service reporting possible.
     */
    #[ORM\Column(length: 32, enumType: IntentTier::class, nullable: true)]
    private ?IntentTier $intentTier = null;

    /** The offer this lead came from. Null only for legacy rows. */
    #[ORM\ManyToOne(targetEntity: Service::class, inversedBy: 'inquiries')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Service $service = null;

    /** Which funnel shape produced this lead, kept for reporting. */
    #[ORM\Column(length: 32, enumType: FunnelTrack::class, nullable: true)]
    private ?FunnelTrack $funnelTrack = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Choice(choices: [self::TYPE_QUOTATION, self::TYPE_MEETING])]
    private ?string $inquiryType = null;

    #[ORM\Column(length: 32, enumType: LeadStatus::class)]
    private LeadStatus $status = LeadStatus::New;

    /**
     * Pipeline valuation in whole USD, set once at submission from the tier
     * defaults. Stored rather than derived so historical figures stay stable
     * if tier pricing later changes.
     */
    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $estimatedValueUsd = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $message = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Assert\GreaterThanOrEqual('today', message: 'Please choose today or a future date.')]
    private ?\DateTimeInterface $preferredDate = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /** Optional by design: the deck may not know the programme yet. */
    #[ORM\ManyToOne(targetEntity: TrainingProgram::class, inversedBy: 'inquiries')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Assert\NotNull(groups: [self::GROUP_TRACK], message: 'Please choose a training program.')]
    private ?TrainingProgram $trainingProgram = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    public function ensureCreatedAt(): void
    {
        $this->createdAt ??= new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getFullName(): ?string { return $this->fullName; }
    public function setFullName(string $fullName): self { $this->fullName = $fullName; return $this; }
    public function getCompanyName(): ?string { return $this->companyName; }
    public function setCompanyName(?string $companyName): self { $this->companyName = $companyName; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }
    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(string $phone): self { $this->phone = $phone; return $this; }
    public function getOrganizationScope(): ?string { return $this->organizationScope; }
    public function setOrganizationScope(?string $scope): self { $this->organizationScope = $scope; return $this; }
    public function getDepartment(): ?string { return $this->department; }
    public function setDepartment(?string $department): self { $this->department = $department; return $this; }
    public function getTeamSize(): ?string { return $this->teamSize; }
    public function setTeamSize(?string $size): self { $this->teamSize = $size; return $this; }
    public function getPreferredChannel(): ?string { return $this->preferredChannel; }
    public function setPreferredChannel(?string $channel): self { $this->preferredChannel = $channel; return $this; }
    public function getService(): ?Service { return $this->service; }
    public function setService(?Service $service): self { $this->service = $service; return $this; }
    public function getFunnelTrack(): ?FunnelTrack { return $this->funnelTrack; }
    public function setFunnelTrack(?FunnelTrack $track): self { $this->funnelTrack = $track; return $this; }

    /**
     * The validation groups to run for this lead's funnel.
     *
     * The corporate-domain rule is only added when the track asks for it, which
     * is what lets an individual enrolee submit a personal mailbox while a
     * corporate buyer must use a work address.
     *
     * @return list<string>
     */
    public function validationGroups(): array
    {
        // "Default" must be listed explicitly: constraints declared without a
        // group live there, and passing any group replaces the default rather
        // than adding to it.
        $groups = ['Default', self::GROUP_DECK];

        if ($this->funnelTrack?->requiresCorporateEmail() ?? true) {
            $groups[] = self::GROUP_CORPORATE;
        }

        if ($this->funnelTrack?->asksForOrganisation() ?? true) {
            $groups[] = self::GROUP_ORGANISATION;
        }

        return $groups;
    }

    public function getIntentTier(): ?IntentTier { return $this->intentTier; }
    public function setIntentTier(?IntentTier $tier): self { $this->intentTier = $tier; return $this; }
    public function getInquiryType(): ?string { return $this->inquiryType; }
    public function setInquiryType(?string $type): self { $this->inquiryType = $type; return $this; }
    public function getStatus(): LeadStatus { return $this->status; }
    public function setStatus(LeadStatus $status): self { $this->status = $status; return $this; }
    public function getEstimatedValueUsd(): ?int { return $this->estimatedValueUsd; }
    public function setEstimatedValueUsd(?int $value): self { $this->estimatedValueUsd = $value; return $this; }
    public function getMessage(): ?string { return $this->message; }
    public function setMessage(?string $message): self { $this->message = $message; return $this; }
    public function getPreferredDate(): ?\DateTimeInterface { return $this->preferredDate; }
    public function setPreferredDate(?\DateTimeInterface $date): self { $this->preferredDate = $date; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function getTrainingProgram(): ?TrainingProgram { return $this->trainingProgram; }
    public function setTrainingProgram(?TrainingProgram $program): self { $this->trainingProgram = $program; return $this; }
}
