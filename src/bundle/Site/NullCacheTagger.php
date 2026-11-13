<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Site;

use Masilia\Contracts\IbexaBlocks\CacheTagger;

/** No HTTP cache of Ibexa to tell. */
final readonly class NullCacheTagger implements CacheTagger
{
    public function tagContents(array $contentIds): void
    {
    }

    public function tagLocations(array $locationIds): void
    {
    }
}
