<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Theme;

/** How a theme shows one content type in one view type: the template, and the image variation each field is shown at. */
final readonly class ViewDefinition
{
    /**
     * @param string                $template   Relative to the templates of the theme ("blocks/hero_banner.twig")
     * @param array<string, string> $variations Field identifier => image variation
     * @param array<string, mixed>  $params     Parameters the template is given besides the fields
     */
    public function __construct(
        public string $template,
        public array $variations = [],
        public array $params = [],
    ) {
    }
}
