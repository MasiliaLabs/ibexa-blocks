<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Theme;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\IbexaBlocks\Theme\ThemeAssets;
use Masilia\IbexaBlocks\Theme\ThemeFavicons;

/**
 * What the themes of the current design say about the layout: the first theme of the chain that says each thing wins, as for the
 * templates, so a theme that changes only its favicons says only that.
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

    public function favicons(): ?string
    {
        return $this->first(static fn ($manifest) => $manifest->favicons);
    }

    /**
     * The icon links of the site: the setting `favicons` of the site if it has one, else what the first theme of the chain says (a
     * folder, of which only the files that are there are linked, or explicit links).
     *
     * @return list<array{rel: string, href: string, sizes?: string, type?: string}>
     */
    public function faviconLinks(): array
    {
        if ($this->config !== null && $this->config->hasParameter('favicons', 'masilia_ibexa_blocks')) {
            $site = ThemeFavicons::fromValue('masilia_ibexa_blocks.favicons', $this->config->getParameter('favicons', 'masilia_ibexa_blocks'));

            return $this->links($site->directory, $site->links);
        }
        foreach ($this->design->themes() as $theme) {
            $manifest = $this->themes->manifest($theme);
            if ($manifest !== null && ($manifest->favicons !== null || $manifest->faviconLinks !== null)) {
                return $this->links($manifest->favicons, $manifest->faviconLinks);
            }
        }

        return [];
    }

    /** @return list<array{rel: string, href: string, sizes?: string, type?: string}> */
    private function links(?string $directory, ?array $links): array
    {
        if ($links !== null) {
            return $links;
        }

        return $directory === null || $directory === '' || $this->faviconSet === null ? [] : $this->faviconSet->links($directory);
    }

    /** Where the font files of a stylesheet host are, when the host serves the stylesheet and the files from two places. */
    private const FONT_FILE_HOSTS = ['fonts.googleapis.com' => 'fonts.gstatic.com'];

    /**
     * The origins the browser should connect to early for the web fonts of the theme: the host of each https stylesheet, and the
     * host of its font files when it is another one. A stylesheet of the project (a path) needs none.
     *
     * @return list<array{origin: string, crossorigin: bool}>
     */
    public function fontOrigins(): array
    {
        $origins = [];
        foreach ($this->fonts() as $font) {
            $host = str_starts_with($font, 'https://') ? parse_url($font, PHP_URL_HOST) : null;
            if (!is_string($host) || $host === '') {
                continue;
            }
            $origins['https://' . $host] ??= ['origin' => 'https://' . $host, 'crossorigin' => false];
            if (isset(self::FONT_FILE_HOSTS[$host])) {
                $origins['https://' . self::FONT_FILE_HOSTS[$host]] ??= ['origin' => 'https://' . self::FONT_FILE_HOSTS[$host], 'crossorigin' => true];
            }
        }

        return array_values($origins);
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
     * @param callable(\Masilia\IbexaBlocks\Theme\ThemeManifest): T|null $read
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
