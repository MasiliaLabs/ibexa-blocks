<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Block;

use Masilia\Bundle\IbexaBlocks\Theme\DesignChain;
use Masilia\Bundle\IbexaBlocks\Theme\ThemeRegistry;

/** How the current design shows a content type in a view type: the first theme of the design that says it. */
final readonly class BlockRegistry
{
    public function __construct(
        private ThemeRegistry $themes,
        private DesignChain $design,
    ) {
    }

    public function find(string $viewType, string $contentType): ?ResolvedView
    {
        foreach ($this->design->themes() as $theme) {
            $definition = $this->themes->manifest($theme)?->view($viewType, $contentType);
            if ($definition !== null) {
                return new ResolvedView($definition, $theme);
            }
        }

        return null;
    }

    /**
     * @return list<string> The themes of the design that were looked in, for a message
     */
    public function searched(): array
    {
        return $this->design->themes();
    }
}
