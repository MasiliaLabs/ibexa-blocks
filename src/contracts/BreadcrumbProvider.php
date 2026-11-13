<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaBlocks;

use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Masilia\IbexaBlocks\Value\Link;

/** The trail from the root of the site to a location. */
interface BreadcrumbProvider
{
    /**
     * @return list<Link> From the root of the site to the location itself
     */
    public function forLocation(Location $location): array;
}
