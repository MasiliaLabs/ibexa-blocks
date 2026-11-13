<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Theme;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\Bundle\IbexaBlocks\Block\BlockRegistry;
use Masilia\Contracts\IbexaBlocks\ShownBlocks;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The block types a design shows, read from the themes of its chain: a template named after the type in blocks/, or a line of
 * a theme.yaml. It reads the files of the themes, so it can answer for a siteaccess other than the current one.
 */
final readonly class ThemeShownBlocks implements ShownBlocks
{
    /**
     * @param array<string, list<string>> $designs
     */
    public function __construct(
        private ThemeRegistry $themes,
        private ConfigResolverInterface $config,
        #[Autowire('%ibexa.design.list%')]
        private array $designs,
    ) {
    }

    public function forSiteaccess(string $siteaccess): array
    {
        $design = (string) $this->config->getParameter('design', null, $siteaccess);
        $chain = array_values(array_unique([$design, ...(array) ($this->designs[$design] ?? [])]));
        $folder = BlockRegistry::folders('blocks')[0];

        $types = [];
        foreach ($chain as $theme) {
            foreach (array_keys($this->themes->manifest($theme)?->views['blocks'] ?? []) as $type) {
                $types[$type] = true;
            }
            foreach ($this->themes->directories() as $directory) {
                foreach (glob(sprintf('%s/%s/%s/*.twig', rtrim($directory, '/'), $theme, $folder)) ?: [] as $file) {
                    $types[basename($file, '.twig')] = true;
                }
            }
        }
        unset($types[BlockRegistry::ANY]);
        $types = array_keys($types);
        sort($types);

        return $types;
    }
}
