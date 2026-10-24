<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ContentBlockRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An editable block of page copy, addressed by a stable key.
 *
 * The project requires that every section a visitor reads is editable from the
 * admin rather than living in a template. A block is looked up by key (for
 * example "home.hero"), so a template asks for the copy it needs and falls back
 * to nothing if the block is missing, rather than the copy being baked in.
 *
 * Keys are dotted and lower case by convention: "<page>.<section>".
 */
#[ORM\Entity(repositoryClass: ContentBlockRepository::class)]
#[ORM\Table(name: 'gdc_content_blocks')]
class ContentBlock
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'block_key', length: 120, unique: true)]
    #[Assert\NotBlank, Assert\Length(max: 120)]
    #[Assert\Regex(
        pattern: '/^[a-z0-9]+(\.[a-z0-9_]+)+$/',
        message: 'Key must be dotted lower case, e.g. "home.hero".',
    )]
    private ?string $blockKey = null;

    /** Human name shown in the admin list. */
    #[ORM\Column(length: 150)]
    #[Assert\NotBlank, Assert\Length(max: 150)]
    private ?string $label = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $heading = null;

    /** The eyebrow above the heading, where the design uses one. */
    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $eyebrow = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 4000)]
    private ?string $body = null;

    #[ORM\Column(length: 60, nullable: true)]
    #[Assert\Length(max: 60)]
    private ?string $ctaLabel = null;

    /**
     * Where the CTA points. A route name, a service slug, or an anchor; the
     * template decides how to resolve it so this stays a plain string.
     */
    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $ctaTarget = null;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getBlockKey(): ?string { return $this->blockKey; }
    public function setBlockKey(string $key): self { $this->blockKey = $key; return $this; }

    public function getLabel(): ?string { return $this->label; }
    public function setLabel(string $label): self { $this->label = $label; return $this; }

    public function getHeading(): ?string { return $this->heading; }
    public function setHeading(?string $heading): self { $this->heading = $heading; return $this; }

    public function getEyebrow(): ?string { return $this->eyebrow; }
    public function setEyebrow(?string $eyebrow): self { $this->eyebrow = $eyebrow; return $this; }

    public function getBody(): ?string { return $this->body; }
    public function setBody(?string $body): self { $this->body = $body; return $this; }

    public function getCtaLabel(): ?string { return $this->ctaLabel; }
    public function setCtaLabel(?string $label): self { $this->ctaLabel = $label; return $this; }

    public function getCtaTarget(): ?string { return $this->ctaTarget; }
    public function setCtaTarget(?string $target): self { $this->ctaTarget = $target; return $this; }

    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): self { $this->active = $active; return $this; }

    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $order): self { $this->sortOrder = $order; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    public function __toString(): string
    {
        return (string) ($this->label ?? '');
    }
}
