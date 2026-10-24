<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ContentBlock;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Edits one content block.
 *
 * Every field is optional except the key and label: a block may legitimately be
 * a heading with no body, or a body with no action. The template decides what to
 * render, so the form does not force an editor to fill in fields the section
 * does not use.
 */
final class ContentBlockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('blockKey', TextType::class, [
                'label' => 'Key',
                'help' => 'The stable address a template asks for, such as "home.hero". Changing it disconnects the block from the page.',
                'disabled' => $options['is_new'] === false,
            ])
            ->add('label', TextType::class, [
                'label' => 'Label',
                'help' => 'What this block is called in the admin. Never shown to visitors.',
            ])
            ->add('eyebrow', TextType::class, [
                'label' => 'Eyebrow',
                'required' => false,
                'help' => 'The small uppercase line above the heading.',
            ])
            ->add('heading', TextType::class, [
                'label' => 'Heading',
                'required' => false,
            ])
            ->add('headingAccent', TextType::class, [
                'label' => 'Heading (accent half)',
                'required' => false,
                'help' => 'Optional. Shown after the heading in the brand orange, as on the home hero.',
            ])
            ->add('body', TextareaType::class, [
                'label' => 'Body',
                'required' => false,
                'attr' => ['rows' => 4],
            ])
            ->add('ctaLabel', TextType::class, [
                'label' => 'Action label',
                'required' => false,
            ])
            ->add('ctaTarget', TextType::class, [
                'label' => 'Action target',
                'required' => false,
                'help' => 'An anchor (#contact), a path (/services), a service slug, a full URL, or "funnel" to open the enquiry deck.',
            ])
            ->add('secondaryCtaLabel', TextType::class, [
                'label' => 'Second action label',
                'required' => false,
            ])
            ->add('secondaryCtaTarget', TextType::class, [
                'label' => 'Second action target',
                'required' => false,
            ])
            ->add('image', TextType::class, [
                'label' => 'Image path',
                'required' => false,
                'help' => 'A path under public/, such as images/work/example.webp. Leave empty to use the section\'s branded panel.',
            ])
            ->add('imageAlt', TextType::class, [
                'label' => 'Image description',
                'required' => false,
                'help' => 'Read aloud to visitors who cannot see the image.',
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Show this section',
                'required' => false,
                'help' => 'Turn off to remove the section from the page entirely.',
            ])
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Order',
                'help' => 'Lower numbers come first.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContentBlock::class,
            'is_new' => true,
        ]);
        $resolver->setAllowedTypes('is_new', 'bool');
    }
}
