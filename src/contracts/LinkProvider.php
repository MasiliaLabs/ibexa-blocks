<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaBlocks;

use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Masilia\IbexaBlocks\Value\Link;

/** How a location is linked to from a page. */
interface LinkProvider
{
    public function forLocation(Location $location): Link;

    public function urlForLocation(int $locationId): string;
}
