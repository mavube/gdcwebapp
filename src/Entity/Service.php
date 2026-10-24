<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\FunnelTrack;
use App\Enum\Pillar;
use App\Repository\ServiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A single offer: one card in a pillar, with its own detail page and its own
 * funnel.
 *
 * Every string a visitor reads lives here rather than in a template, so
 * marketing copy can be changed from the admin without a deploy. That is a hard
 * requirement for this project, not a preference.
 */
#[ORM\Entity(repositoryClass: ServiceRepository::class)]
#[ORM\Table(name: 'gdc_services')]
class Service
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150, unique: true)]
    #[Assert\NotBlank, Assert\Length(max: 150)]
    #[Assert\Regex(pattern: '/^[a-z0-9-]+$/', message: 'Slug may only contain lowercase letters, numbers and hyphens.')]
    private ?string $slug = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank, Assert\Length(max: 255)]
    private ?string $title = null;

    /** One sentence under the title on the card. */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank, Assert\Length(max: 255)]
    private ?string $tagline = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private ?string $description = null;

    #[ORM\Column(length: 32, enumType: Pillar::class)]
    #[Assert\NotNull]
    private ?Pillar $pillar = null;

    /**
     * Which funnel shape this offer uses. Defaults from the pillar's
     * organisational flag but is overridable per service, because the split is
     * commercial rather than structural: a construction governance course sells
     * to companies even though it sits in the certification pillar.
     */
    #[ORM\Column(length: 32, enumType: FunnelTrack::class)]
    #[Assert\NotNull]
    private FunnelTrack $funnelTrack = FunnelTrack::Individual;

    /** Short factual badges shown on the card and detail hero. */
    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $duration = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $deliveryMethod = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $pduCount = null;

    /** Display price, e.g. "USD 1,000". Null when the offer is quoted only. */
    #[ORM\Column(length: 60, nullable: true)]
    #[Assert\Length(max: 60)]
    private ?string $priceLabel = null;

    #[ORM\Column(length: 60, nullable: true)]
    #[Assert\Length(max: 60)]
    private ?string $priceNote = null;

    /** Minimum participants, where the offer is sold to a group. */
    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $minimumParticipants = null;

    /** Label for the card's call to action. */
    #[ORM\Column(length: 60)]
    #[Assert\NotBlank, Assert\Length(max: 60)]
    private string $ctaLabel = 'Learn more';

    /** Marks the one offer per pillar that gets the highlighted treatment. */
    #[ORM\Column]
    private bool $featured = false;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    #[Assert\NotNull]
    private int $sortOrder = 0;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, CourseInquiry> */
    #[ORM\OneToMany(targetEntity: CourseInquiry::class, mappedBy: 'service')]
    private Collection $inquiries;

    public function __construct()
    {
        $this->inquiries = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = $slug; return $this; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }

    public function getTagline(): ?string { return $this->tagline; }
    public function setTagline(string $tagline): self { $this->tagline = $tagline; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(string $description): self { $this->description = $description; return $this; }

    public function getPillar(): ?Pillar { return $this->pillar; }
    public function setPillar(Pillar $pillar): self { $this->pillar = $pillar; return $this; }

    public function getFunnelTrack(): FunnelTrack { return $this->funnelTrack; }
    public function setFunnelTrack(FunnelTrack $track): self { $this->funnelTrack = $track; return $this; }

    public function getDuration(): ?string { return $this->duration; }
    public function setDuration(?string $duration): self { $this->duration = $duration; return $this; }

    public function getDeliveryMethod(): ?string { return $this->deliveryMethod; }
    public function setDeliveryMethod(?string $method): self { $this->deliveryMethod = $method; return $this; }

    public function getPduCount(): ?int { return $this->pduCount; }
    public function setPduCount(?int $count): self { $this->pduCount = $count; return $this; }

    public function getPriceLabel(): ?string { return $this->priceLabel; }
    public function setPriceLabel(?string $label): self { $this->priceLabel = $label; return $this; }

    public function getPriceNote(): ?string { return $this->priceNote; }
    public function setPriceNote(?string $note): self { $this->priceNote = $note; return $this; }

    public function getMinimumParticipants(): ?int { return $this->minimumParticipants; }
    public function setMinimumParticipants(?int $minimum): self { $this->minimumParticipants = $minimum; return $this; }

    public function getCtaLabel(): string { return $this->ctaLabel; }
    public function setCtaLabel(string $label): self { $this->ctaLabel = $label; return $this; }

    public function isFeatured(): bool { return $this->featured; }
    public function setFeatured(bool $featured): self { $this->featured = $featured; return $this; }

    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): self { $this->active = $active; return $this; }

    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $order): self { $this->sortOrder = $order; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    /** @return Collection<int, CourseInquiry> */
    public function getInquiries(): Collection { return $this->inquiries; }

    public function __toString(): string
    {
        return (string) ($this->title ?? '');
    }
}
