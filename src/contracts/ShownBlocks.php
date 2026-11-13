<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaBlocks;

/** Which block types the front office of a siteaccess can show: the ones its design has a template for. */
interface ShownBlocks
{
    /**
     * @return list<string> The content types the design of the siteaccess shows in the "blocks" view
     */
    public function forSiteaccess(string $siteaccess): array;
}
