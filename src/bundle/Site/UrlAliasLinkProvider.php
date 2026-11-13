<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Site;

use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Core\MVC\Symfony\Routing\UrlAliasRouter;
use Masilia\Contracts\IbexaBlocks\LinkProvider;
use Masilia\IbexaBlocks\Value\Link;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/** Links to a location by its URL alias, on the site the page is shown on. */
final readonly class UrlAliasLinkProvider implements LinkProvider
{
    public function __construct(private RouterInterface $router)
    {
    }

    public function forLocation(Location $location): Link
    {
        $info = $location->getContentInfo();

        return new Link(
            $this->urlForLocation($location->getId()),
            $info->getName(),
            ['identifier' => $info->getContentType()->identifier],
        );
    }

    public function urlForLocation(int $locationId, ?string $siteaccess = null): string
    {
        $parameters = ['locationId' => $locationId] + ($siteaccess !== null ? ['siteaccess' => $siteaccess] : []);

        return $this->router->generate(UrlAliasRouter::URL_ALIAS_ROUTE_NAME, $parameters, UrlGeneratorInterface::ABSOLUTE_PATH);
    }
}
