<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Site;

use Ibexa\HttpCache\Handler\TagHandler;
use Masilia\Contracts\IbexaBlocks\CacheTagger;

/** Tags the response with the contents and locations it was made from, so that Ibexa's HTTP cache purges it when they change. */
final readonly class IbexaCacheTagger implements CacheTagger
{
    public function __construct(private TagHandler $handler)
    {
    }

    public function tagContents(array $contentIds): void
    {
        $this->handler->addContentTags($contentIds);
    }

    public function tagLocations(array $locationIds): void
    {
        $this->handler->addLocationTags($locationIds);
    }
}
