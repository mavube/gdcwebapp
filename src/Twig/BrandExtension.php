<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\FunnelConfigurator;
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
        ];
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
