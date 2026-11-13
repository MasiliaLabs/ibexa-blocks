<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Block;

use Masilia\IbexaBlocks\Theme\ViewDefinition;

/** How a content is shown: the definition, the full name of its template, and the theme that named it (null when found by convention). */
final readonly class ResolvedView
{
    public function __construct(
        public ViewDefinition $definition,
        public string $template,
        public ?string $theme = null,
    ) {
    }
}
