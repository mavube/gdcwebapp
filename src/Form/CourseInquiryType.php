<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CourseInquiry;
use App\Entity\TrainingProgram;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CourseInquiryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add("fullName", TextType::class, ["label" => "Full name"])
            ->add("companyName", TextType::class, ["label" => "Company"])
            ->add("email", EmailType::class, ["label" => "Work email"])
            ->add("phone", TextType::class, ["label" => "Phone"])
            ->add("inquiryType", ChoiceType::class, [
                "label" => "I would like",
                "choices" => [
                    "A written quotation" => CourseInquiry::TYPE_QUOTATION,
                    "To book a meeting" => CourseInquiry::TYPE_MEETING,
                ],
            ])
            ->add("preferredDate", DateType::class, [
                "label" => "Preferred date (optional)",
                "widget" => "single_text",
                "required" => false,
            ])
            ->add("trainingProgram", EntityType::class, [
                "class" => TrainingProgram::class,
                "choice_label" => "title",
                "label" => "Training program",
            ])
            ->add("submit", SubmitType::class, ["label" => "Send request"]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            "data_class" => CourseInquiry::class,
        ]);
    }
}
