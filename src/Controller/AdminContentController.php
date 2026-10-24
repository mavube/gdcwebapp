<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ContentBlock;
use App\Entity\ContentItem;
use App\Enum\ContentSection;
use App\Form\ContentBlockType;
use App\Form\ContentItemType;
use App\Repository\ContentBlockRepository;
use App\Repository\ContentItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The content editor.
 *
 * The site's copy lives in the database, so this is the surface that keeps it
 * editable. It covers both entities the public pages read: the named blocks
 * ({@see ContentBlock}) and the rows inside a repeatable list
 * ({@see ContentItem}).
 *
 * Access is enforced twice, as on the telemetry dashboard: access_control in
 * security.yaml gates the path, and the IsGranted attributes re-check at the
 * controller so a future route rename cannot expose it.
 */
#[Route('/admin/content', name: 'app_admin_content_')]
#[IsGranted('ROLE_GDC_ADMIN')]
final class AdminContentController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(ContentBlockRepository $blocks, ContentItemRepository $items): Response
    {
        $sections = [];

        foreach (ContentSection::cases() as $section) {
            $sections[$section->value] = [
                'case' => $section,
                'rows' => $items->findActiveFor($section),
            ];
        }

        return $this->render('admin/content/index.html.twig', [
            'blocks' => $blocks->findBy([], ['sortOrder' => 'ASC', 'blockKey' => 'ASC']),
            'sections' => $sections,
        ]);
    }

    // ------------------------------------------------------------------ blocks

    #[Route('/blocks/{id}/edit', name: 'block_edit', methods: ['GET', 'POST'])]
    public function editBlock(ContentBlock $block, Request $request, EntityManagerInterface $em): Response
    {
        return $this->handleBlock($block, $request, $em, false);
    }

    #[Route('/blocks/new', name: 'block_new', methods: ['GET', 'POST'])]
    public function newBlock(Request $request, EntityManagerInterface $em): Response
    {
        return $this->handleBlock(new ContentBlock(), $request, $em, true);
    }

    private function handleBlock(ContentBlock $block, Request $request, EntityManagerInterface $em, bool $isNew): Response
    {
        $form = $this->createForm(ContentBlockType::class, $block, ['is_new' => $isNew]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($isNew) {
                $em->persist($block);
            }
            $em->flush();

            $this->addFlash('success', $isNew ? 'Section created.' : 'Section updated.');

            return $this->redirectToRoute('app_admin_content_index');
        }

        return $this->render('admin/content/block_form.html.twig', [
            'form' => $form,
            'block' => $block,
            'is_new' => $isNew,
        ]);
    }

    // ------------------------------------------------------------------- items

    #[Route('/sections/{section}/items/new', name: 'item_new', methods: ['GET', 'POST'])]
    public function newItem(string $section, Request $request, EntityManagerInterface $em, ContentItemRepository $items): Response
    {
        $case = $this->resolveSection($section);

        $item = new ContentItem();
        $item->setSection($case);
        // New rows go to the end of their list rather than the top, so adding
        // one does not silently reorder the section a visitor is reading.
        $item->setSortOrder(($items->countActiveFor($case) + 1) * 10);

        return $this->handleItem($item, $case, $request, $em, true);
    }

    #[Route('/items/{id}/edit', name: 'item_edit', methods: ['GET', 'POST'])]
    public function editItem(ContentItem $item, Request $request, EntityManagerInterface $em): Response
    {
        $case = $item->getSection();

        if ($case === null) {
            throw $this->createNotFoundException('That row has no section.');
        }

        return $this->handleItem($item, $case, $request, $em, false);
    }

    private function handleItem(ContentItem $item, ContentSection $case, Request $request, EntityManagerInterface $em, bool $isNew): Response
    {
        $form = $this->createForm(ContentItemType::class, $item, ['field_hint' => $case->fieldHint()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($isNew) {
                $em->persist($item);
            }
            $em->flush();

            $this->addFlash('success', $isNew ? 'Row added.' : 'Row updated.');

            return $this->redirectToRoute('app_admin_content_index', ['_fragment' => $case->value]);
        }

        return $this->render('admin/content/item_form.html.twig', [
            'form' => $form,
            'item' => $item,
            'section' => $case,
            'is_new' => $isNew,
        ]);
    }

    private function resolveSection(string $section): ContentSection
    {
        $case = ContentSection::tryFrom($section);

        if ($case === null) {
            throw $this->createNotFoundException('That section does not exist.');
        }

        return $case;
    }
}
