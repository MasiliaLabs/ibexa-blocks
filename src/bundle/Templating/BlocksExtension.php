<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Templating;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** `{{ render_blocks(page.blocks) }}` in a page template. */
final class BlocksExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('render_blocks', [BlocksRuntime::class, 'render'], ['is_safe' => ['html']]),
        ];
    }
}
