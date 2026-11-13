<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaBlocks;

/**
 * The menus of the layout (header, footer…). Where they are edited is the project's business: the menu manager, a content tree,
 * a file. What a template gets is whatever it knows how to print, for the project's themes.
 */
interface MenuProvider
{
    /**
     * @param string $type           The type of menu ("main", "menu_footer"), as the layout settings name them
     * @param int    $rootLocationId The root of the site the menu belongs to
     *
     * @return mixed The menu a template prints, or null when the site has none
     */
    public function menu(string $type, int $rootLocationId): mixed;
}
