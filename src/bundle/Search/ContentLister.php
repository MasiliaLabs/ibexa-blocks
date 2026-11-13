<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Search;

use Masilia\Bundle\IbexaBlocks\Content\RenderableContent;

/** Lists contents of a type for a block that fills itself. */
interface ContentLister
{
    /**
     * @return list<RenderableContent>
     */
    public function of(string $contentType, int $limit): array;
}
