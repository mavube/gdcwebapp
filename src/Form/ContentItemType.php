<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ContentItem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Edits one content row.
 *
 * The field labels are generic because one entity serves every list; the help
 * text for the row's own section is passed in as `field_hint`, so the editor is
 * told that "title" means "the question" in one list and "the author" in
 * another.
 */
final class ContentItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $hint = $options['field_hint'];

        $builder
            ->add('title', TextType::class, [
                'label' => 'Title',
                'required' => false,
                'help' => $hint,
            ])
            ->add('subtitle', TextType::class, [
                'label' => 'Subtitle',
                'required' => false,
            ])
            ->add('body', TextareaType::class, [
                'label' => 'Body',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('image', TextType::class, [
                'label' => 'Image path',
                'required' => false,
                'help' => 'A path under public/, such as images/work/example.webp.',
            ])
            ->add('imageAlt', TextType::class, [
                'label' => 'Image description',
                'required' => false,
                'help' => 'Read aloud to visitors who cannot see the image.',
            ])
            ->add('linkLabel', TextType::class, [
                'label' => 'Link label',
                'required' => false,
            ])
            ->add('linkTarget', TextType::class, [
                'label' => 'Link target',
                'required' => false,
                'help' => 'An anchor (#faq), a path (/services), a service slug, a full URL, or "route:name" for a named route.',
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Show this row',
                'required' => false,
            ])
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Order',
                'help' => 'Lower numbers come first.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContentItem::class,
            'field_hint' => null,
        ]);
        $resolver->setAllowedTypes('field_hint', ['null', 'string']);
    }
}
