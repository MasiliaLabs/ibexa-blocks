<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Block;

use Masilia\IbexaBlocks\Theme\ViewDefinition;

/** A view definition and the theme that provided it. */
final readonly class ResolvedView
{
    public function __construct(
        public ViewDefinition $definition,
        public string $theme,
    ) {
    }
}
