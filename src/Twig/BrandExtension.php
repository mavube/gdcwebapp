<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\FunnelConfigurator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Presentation helpers that depend on files on disk.
 */
final class BrandExtension extends AbstractExtension
{
    public function __construct(
        private readonly string $projectDir,
        private readonly FunnelConfigurator $configurator,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('gdc_logo_available', $this->logoAvailable(...)),
            // A function rather than a Twig global: globals are evaluated when
            // the environment boots, which would run a database query on every
            // request including error pages, where a failing query would hide
            // the original error.
            new TwigFunction('gdc_funnel_config', $this->funnelConfig(...)),
            new TwigFunction('gdc_link', $this->link(...)),
            new TwigFunction('gdc_image', $this->image(...)),
            new TwigFunction('gdc_is_external', $this->isExternal(...)),
        ];
    }

    /**
     * Resolves a stored link target to a URL.
     *
     * Content rows store their destination as a plain string so the editor is
     * not bound to the route table. This decides what it means, in order:
     *
     *  - empty                    → null, so the caller omits the link entirely
     *  - an anchor ("#faq")       → used as-is
     *  - a path ("/services")     → used as-is
     *  - any other URI scheme     → used as-is (tel:, mailto:, sms:); see isExternal()
     *  - a named route            → generated, so a link survives a path change
     *  - anything else            → treated as a service slug
     *
     * The service-slug fallback exists because pointing a row at an offer is the
     * common case. The admin form checks the slug is live before saving, so this
     * does not re-query on every render; an unknown slug 404s like any bad link.
     */
    public function link(?string $target): ?string
    {
        $target = trim((string) $target);

        if ($target === '') {
            return null;
        }

        if (str_starts_with($target, '#') || str_starts_with($target, '/')) {
            return $target;
        }

        // Any explicit scheme is already a complete URL: http(s), tel:, mailto:,
        // sms:. Without this the slug fallback below would turn a contact link
        // into "/services/tel:+255...", which is a dead page.
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $target) === 1) {
            if (str_starts_with($target, 'route:')) {
                return $this->urlGenerator->generate(substr($target, 6));
            }

            return $target;
        }

        return '/services/'.$target;
    }

    /**
     * Resolves a stored image path to a public URL.
     *
     * Accepts a path under public/ ("images/work/example.webp") or an absolute
     * URL. Returns null when the file is named but missing, so a template can
     * fall back to its placeholder instead of rendering a broken image.
     */
    public function image(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        $relative = ltrim($path, '/');

        return is_file($this->projectDir.'/public/'.$relative) ? '/'.$relative : null;
    }

    /**
     * Whether a stored link points outside the site, so the template can add
     * rel="noreferrer" and a target.
     */
    public function isExternal(?string $target): bool
    {
        return preg_match('#^https?://#i', trim((string) $target)) === 1;
    }

    /**
     * The conversion deck's configuration, for the given service if any.
     *
     * @return array<string, mixed>
     */
    public function funnelConfig(?\App\Entity\Service $service = null): array
    {
        return $this->configurator->build($service);
    }

    /**
     * Whether a coloured brand mark has been dropped in.
     *
     * The only logo supplied so far is a white knockout, which is invisible on
     * the light header. Rather than hard-code a decision the owner cannot
     * change, the template asks this question: add images/gdc-logo.svg and the
     * header switches to the real mark with no code change.
     */
    public function logoAvailable(): bool
    {
        return is_file($this->projectDir.'/public/images/gdc-logo.svg');
    }
}
