<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Theme;

use Masilia\IbexaBlocks\Theme\ThemeAssets;

/**
 * What the themes of the current design say about the layout: the first theme of the chain that says each thing wins, as for the
 * templates, so a theme that changes only its favicons says only that.
 */
final readonly class ThemeSettings
{
    public function __construct(
        private ThemeRegistry $themes,
        private DesignChain $design,
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
