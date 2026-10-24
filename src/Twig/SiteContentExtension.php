<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\ContentBlock;
use App\Enum\ContentSection;
use App\Repository\ContentBlockRepository;
use App\Repository\ContentItemRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Site-wide content rows, for the shell every page shares.
 *
 * The header and footer are rendered by base.html.twig, which no controller
 * prepares data for: it is included by every template. Passing the shell's rows
 * from each controller would mean every new action had to remember to do it, and
 * a page that forgot would silently render a header with no navigation.
 *
 * So the shell asks this extension instead. It is a function rather than a Twig
 * global for the same reason gdc_funnel_config is: globals are evaluated when
 * the Twig environment boots, which would run a query on every request
 * including error pages, where a failing query would mask the original error.
 *
 * The repository memoises per section, so a page that asks for the same list
 * twice — the header and the footer both read the navigation — issues one query.
 */
final class SiteContentExtension extends AbstractExtension
{
    /** @var array<string, \App\Entity\ContentBlock|null> */
    private array $blockMemo = [];

    public function __construct(
        private readonly ContentItemRepository $items,
        private readonly ContentBlockRepository $blocks,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('gdc_site_items', $this->items(...)),
            new TwigFunction('gdc_site_block', $this->block(...)),
        ];
    }

    /**
     * One active block by key, for the shell.
     *
     * Returns null rather than throwing when the key is unknown or the block is
     * inactive, so a template can render a fallback. The result is memoised: the
     * shell reads the same key on every page.
     */
    public function block(string $key): ?ContentBlock
    {
        return $this->blockMemo[$key] ??= $this->blocks->findOneBy(['blockKey' => $key, 'active' => true]);
    }

    /**
     * The active rows for one site-wide section, in display order.
     *
     * @return list<\App\Entity\ContentItem>
     */
    public function items(string $section): array
    {
        $case = ContentSection::tryFrom($section);

        return $case === null ? [] : $this->items->findActiveFor($case);
    }
}
