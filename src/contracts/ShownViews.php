<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaBlocks;

/** Which content types the front office of a siteaccess can show in a view: the ones its design has a template for. */
interface ShownViews
{
    /**
     * @param string $view "blocks" (a block of a page), "block_item" (a card in a list), "line", "embed" or "full"
     *
     * @return list<string>
     */
    public function contentTypes(string $siteaccess, string $view): array;
}
