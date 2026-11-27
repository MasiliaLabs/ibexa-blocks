<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Theme;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\IbexaBlocks\Theme\ThemeAssets;
use Masilia\IbexaBlocks\Theme\ThemeFavicons;

/**
 * What the themes of the current design say about the layout: the first theme of the chain that says each thing wins, as for the
 * templates, so a theme that changes only its fonts says only that.
 */
final readonly class ThemeSettings
{
    public function __construct(
        private ThemeRegistry $themes,
        private DesignChain $design,
        private ?FaviconSet $faviconSet = null,
        private ?ConfigResolverInterface $config = null,
    ) {
    }

    public function assets(): ?ThemeAssets
    {
        return $this->first(static fn ($manifest) => $manifest->assets);
    }

    /**
     * The icon links of the site: its setting `favicons` (a folder, of which only the files that are there are linked, or explicit
     * links); the icons are the site's, not the theme's, so that two sites of one design have their own.
     *
     * @return list<array{rel: string, href: string, sizes?: string, type?: string}>
     */
    public function faviconLinks(): array
    {
        if ($this->config === null || !$this->config->hasParameter('favicons', 'masilia_ibexa_blocks')) {
            return [];
        }
        $site = ThemeFavicons::fromValue('masilia_ibexa_blocks.favicons', $this->config->getParameter('favicons', 'masilia_ibexa_blocks'));

        return $this->links($site->directory, $site->links);
    }

    /** @return list<array{rel: string, href: string, sizes?: string, type?: string}> */
    private function links(?string $directory, ?array $links): array
    {
        if ($links !== null) {
            return $links;
        }

        return $directory === null || $directory === '' || $this->faviconSet === null ? [] : $this->faviconSet->links($directory);
    }

    /**
     * The origins the layout preconnects to: those of the first theme of the chain that has a `preconnect` (an empty list is
     * saying "none").
     *
     * @return list<array{origin: string, crossorigin: bool}>
     */
    public function preconnect(): array
    {
        return $this->first(static fn ($manifest) => $manifest->preconnect) ?? [];
    }

    /** @return list<string> */
    public function fonts(): array
    {
        return $this->first(static fn ($manifest) => $manifest->fonts !== [] ? $manifest->fonts : null) ?? [];
    }

    public function notifications(): bool
    {
        return $this->first(static fn ($manifest) => $manifest->notifications) ?? true;
    }

    /**
     * @template T
     *
     * @param callable(\Masilia\IbexaBlocks\Theme\ThemeManifest): (T|null) $read
     *
     * @return T|null
     */
    private function first(callable $read): mixed
    {
        foreach ($this->design->themes() as $theme) {
            $manifest = $this->themes->manifest($theme);
            $value = $manifest === null ? null : $read($manifest);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }
}
