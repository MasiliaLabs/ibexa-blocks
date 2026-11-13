<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Site;

use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Masilia\Contracts\IbexaBlocks\BreadcrumbProvider;
use Masilia\Contracts\IbexaBlocks\LinkProvider;

/** The trail from the root of the site (the tree root of the siteaccess) down to a location. */
final readonly class LocationBreadcrumbProvider implements BreadcrumbProvider
{
    public function __construct(
        private LinkProvider $links,
        private ConfigResolverInterface $config,
    ) {
    }

    public function forLocation(Location $location): array
    {
        $root = (int) $this->config->getParameter('content.tree_root.location_id');
        $trail = [$this->links->forLocation($location)];

        // A location outside the site (the media library, say) has no trail but itself.
        if ($root !== $location->getId() && in_array((string) $root, $location->getPath(), true)) {
            $current = $location;
            while (($parent = $current->getParentLocation()) !== null) {
                $trail[] = $this->links->forLocation($parent);
                if ($parent->getId() === $root) {
                    break;
                }
                $current = $parent;
            }
        }

        return array_reverse($trail);
    }
}
