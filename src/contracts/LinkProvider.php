<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaBlocks;

use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Masilia\IbexaBlocks\Value\Link;

/** How a location is linked to from a page. */
interface LinkProvider
{
    public function forLocation(Location $location): Link;

    /** @param string|null $siteaccess The siteaccess the link goes to, when it is not the current one (the same page in another language) */
    public function urlForLocation(int $locationId, ?string $siteaccess = null): string;
}
