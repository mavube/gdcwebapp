<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TrainingProgramRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TrainingProgramRepository::class)]
#[ORM\Table(name: "gdc_training_programs")]
class TrainingProgram
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150, unique: true)]
    #[Assert\NotBlank, Assert\Length(max: 150)]
    #[Assert\Regex(pattern: "/^[a-z0-9-]+$/", message: "Slug may only contain lowercase letters, numbers and hyphens.")]
    private ?string $slug = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank, Assert\Length(max: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank, Assert\Length(max: 255)]
    private ?string $tagline = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank, Assert\Length(max: 50)]
    private ?string $duration = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank, Assert\Length(max: 50)]
    private ?string $deliveryMethod = null;

    #[ORM\Column]
    #[Assert\NotNull, Assert\PositiveOrZero]
    private ?int $pduCount = null;

    #[ORM\Column]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $createdAt = null;

    /** @var Collection<int, CourseInquiry> */
    #[ORM\OneToMany(targetEntity: CourseInquiry::class, mappedBy: "trainingProgram", cascade: ["remove"], orphanRemoval: true)]
    private Collection $inquiries;

    public function __construct()
    {
        $this->inquiries = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
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
    public function getDuration(): ?string { return $this->duration; }
    public function setDuration(string $duration): self { $this->duration = $duration; return $this; }
    public function getDeliveryMethod(): ?string { return $this->deliveryMethod; }
    public function setDeliveryMethod(string $m): self { $this->deliveryMethod = $m; return $this; }
    public function getPduCount(): ?int { return $this->pduCount; }
    public function setPduCount(int $n): self { $this->pduCount = $n; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    /** @return Collection<int, CourseInquiry> */
    public function getInquiries(): Collection { return $this->inquiries; }
}
