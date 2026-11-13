<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaBlocks;

use Masilia\Bundle\IbexaBlocks\Content\RenderableContent;
use Masilia\IbexaBlocks\Block\ViewState;

/**
 * What one kind of block needs besides its fields: the items a grid fills itself with, the markers of a map. It replaces the
 * `if ($blockType === ...)` of a controller: a class declared with #[AsBlockParameters] for the content types it serves.
 */
interface BlockViewParameters
{
    /**
     * @param array<string, mixed> $fields What the earlier steps made of the block's fields and parameters
     *
     * @return array<string, mixed> Parameters the template is given; they win over the fields of the same name
     */
    public function parameters(RenderableContent $block, array $fields, ViewState $view): array;
}
