<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Site;

use Masilia\Contracts\IbexaBlocks\MenuProvider;

/** No menu manager: the layout has no menus. */
final readonly class NullMenuProvider implements MenuProvider
{
    public function menu(string $type, int $rootLocationId): mixed
    {
        return null;
    }
}
