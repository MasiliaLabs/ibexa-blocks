<?php

declare(strict_types=1);

namespace Masilia\Contracts\IbexaBlocks;

/** Tells the HTTP cache which contents and locations a response was made from, so that it is purged when they change. */
interface CacheTagger
{
    /**
     * @param list<int> $contentIds
     */
    public function tagContents(array $contentIds): void;

    /**
     * @param list<int> $locationIds
     */
    public function tagLocations(array $locationIds): void;
}
