<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CourseInquiryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CourseInquiryRepository::class)]
#[ORM\Table(name: "gdc_course_inquiries")]
class CourseInquiry
{
    public const TYPE_QUOTATION = "Quotation";
    public const TYPE_MEETING = "Meeting";

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank, Assert\Length(max: 100)]
    private ?string $fullName = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank, Assert\Length(max: 150)]
    private ?string $companyName = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank, Assert\Email, Assert\Length(max: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank, Assert\Length(max: 30)]
    #[Assert\Regex(pattern: "/^[0-9 +().-]{6,30}$/", message: "Please enter a valid phone number.")]
    private ?string $phone = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank, Assert\Choice(choices: [self::TYPE_QUOTATION, self::TYPE_MEETING])]
    private ?string $inquiryType = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    #[Assert\GreaterThanOrEqual("today", message: "Please choose today or a future date.")]
    private ?\DateTimeInterface $preferredDate = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: TrainingProgram::class, inversedBy: "inquiries")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    #[Assert\NotNull(message: "Please choose a training program.")]
    private ?TrainingProgram $trainingProgram = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getFullName(): ?string { return $this->fullName; }
    public function setFullName(string $fullName): self { $this->fullName = $fullName; return $this; }
    public function getCompanyName(): ?string { return $this->companyName; }
    public function setCompanyName(string $companyName): self { $this->companyName = $companyName; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }
    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(string $phone): self { $this->phone = $phone; return $this; }
    public function getInquiryType(): ?string { return $this->inquiryType; }
    public function setInquiryType(string $type): self { $this->inquiryType = $type; return $this; }
    public function getPreferredDate(): ?\DateTimeInterface { return $this->preferredDate; }
    public function setPreferredDate(?\DateTimeInterface $date): self { $this->preferredDate = $date; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function getTrainingProgram(): ?TrainingProgram { return $this->trainingProgram; }
    public function setTrainingProgram(?TrainingProgram $program): self { $this->trainingProgram = $program; return $this; }
}
