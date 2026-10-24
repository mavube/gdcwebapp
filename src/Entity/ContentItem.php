<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ContentSection;
use App\Repository\ContentItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One row in a repeatable list on a page.
 *
 * Where {@see ContentBlock} holds the copy for a single named section, this
 * holds the rows *inside* a section: the carousel tiles, the numbered offer
 * rows, the accordion entries, the review cards. One entity with generic
 * columns serves every list rather than an entity per section, because the
 * lists differ only in meaning, not shape.
 *
 * Column names are therefore generic — `title`, `subtitle`, `body` — and
 * {@see ContentSection::fieldHint()} records what each one means per section.
 *
 * The project requires that everything a visitor reads is editable from the
 * admin rather than living in a template. A section renders nothing when it has
 * no active rows, so an editor can take a whole list offline by deactivating
 * its rows, and a template never falls back to copy baked into markup.
 */
#[ORM\Entity(repositoryClass: ContentItemRepository::class)]
#[ORM\Table(name: 'gdc_content_items')]
#[ORM\Index(name: 'idx_content_items_section_order', columns: ['section', 'sort_order'])]
class ContentItem
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'section', length: 60, enumType: ContentSection::class)]
    private ?ContentSection $section = null;

    /** The row's primary text: an offer name, a question, an author. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $title = null;

    /** The row's secondary text: a category, a role, a figure's caption. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $subtitle = null;

    /** The row's prose: an answer, a quote, a supporting line. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 4000)]
    private ?string $body = null;

    /**
     * The row's image, as a path under public/ (for example
     * "images/work/example.webp") or an absolute URL.
     *
     * A path rather than a binary: the project commits no image whose rights
     * are undocumented, so an editor points at a file that is already licensed
     * rather than uploading one through the admin.
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $image = null;

    /** Alt text for {@see $image}. Required by the design's accessibility rules. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $imageAlt = null;

    #[ORM\Column(length: 60, nullable: true)]
    #[Assert\Length(max: 60)]
    private ?string $linkLabel = null;

    /**
     * Where the row links. A route name, a service slug, an anchor or a URL;
     * the template resolves it so this stays a plain string.
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $linkTarget = null;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(name: 'sort_order')]
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

    public function getSection(): ?ContentSection { return $this->section; }

    public function setSection(ContentSection $section): self
    {
        $this->section = $section;

        return $this;
    }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $title): self { $this->title = $title; return $this; }

    public function getSubtitle(): ?string { return $this->subtitle; }
    public function setSubtitle(?string $subtitle): self { $this->subtitle = $subtitle; return $this; }

    public function getBody(): ?string { return $this->body; }
    public function setBody(?string $body): self { $this->body = $body; return $this; }

    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $image): self { $this->image = $image; return $this; }

    public function getImageAlt(): ?string { return $this->imageAlt; }
    public function setImageAlt(?string $alt): self { $this->imageAlt = $alt; return $this; }

    public function getLinkLabel(): ?string { return $this->linkLabel; }
    public function setLinkLabel(?string $label): self { $this->linkLabel = $label; return $this; }

    public function getLinkTarget(): ?string { return $this->linkTarget; }
    public function setLinkTarget(?string $target): self { $this->linkTarget = $target; return $this; }

    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): self { $this->active = $active; return $this; }

    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $order): self { $this->sortOrder = $order; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    public function __toString(): string
    {
        return (string) ($this->title ?? $this->body ?? '');
    }
}
